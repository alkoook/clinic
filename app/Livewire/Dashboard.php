<?php

namespace App\Livewire;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PractitionerEarning;
use App\Models\PractitionerSettlement;
use App\Models\Room;
use App\Models\TreatmentSession;
use App\Services\FinancialWeekService;
use App\Services\WeeklyClosingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('لوحة التحكم')]
class Dashboard extends Component
{
    public function render()
    {
        $user = auth()->user();
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $appointmentQuery = Appointment::query()
            ->where('starts_at', '>=', $today)
            ->where('starts_at', '<', $tomorrow)
            ->when($user->hasRole('practitioner'), fn (Builder $query) => $query->where('practitioner_id', $user->practitioner?->id));

        $todayStats = (clone $appointmentQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as scheduled', [AppointmentStatus::Scheduled->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as attended', [AppointmentStatus::Attended->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as completed', [AppointmentStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as no_show', [AppointmentStatus::NoShow->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as cancelled', [AppointmentStatus::Cancelled->value])
            ->first();

        $upcomingAppointments = (clone $appointmentQuery)
            ->with(['practitioner:id,name', 'room:id,name', 'participants.patient:id,full_name'])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        if ($user->hasRole('practitioner')) {
            return view('livewire.dashboard', [
                'mode' => 'practitioner',
                'todayStats' => $todayStats,
                'upcomingAppointments' => $upcomingAppointments,
                'practitionerStats' => $this->practitionerStats($user->practitioner?->id),
            ]);
        }

        $operational = [
            'waiting' => Appointment::query()->where('status', AppointmentStatus::Attended)->whereDate('starts_at', $today)->count(),
            'needs_payment' => TreatmentSession::query()->where('status', 'pending_payment')->whereDate('started_at', $today)->count(),
            'occupied_rooms' => Appointment::query()
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>', now())
                ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Attended])
                ->distinct('room_id')
                ->count('room_id'),
            'active_rooms' => Room::query()->where('is_active', true)->count(),
            'new_patients' => Patient::query()->whereDate('created_at', $today)->count(),
        ];

        if (! $user->can('financial.dashboard.view')) {
            return view('livewire.dashboard', compact('todayStats', 'upcomingAppointments', 'operational') + ['mode' => 'secretary']);
        }

        $financialWeek = app(FinancialWeekService::class)->current();
        $financial = app(WeeklyClosingService::class)->preview($financialWeek);
        $cashboxes = Cashbox::query()
            ->select('cashboxes.*')
            ->selectSub(
                CashboxTransaction::query()
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)")
                    ->whereColumn('cashbox_id', 'cashboxes.id'),
                'balance',
            )
            ->orderBy('id')
            ->get();

        $trend = Payment::query()
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total')
            ->where('paid_at', '>=', now()->subDays(6)->startOfDay())
            ->where('status', '!=', 'voided')
            ->groupByRaw('DATE(paid_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $topPractitioners = PractitionerEarning::query()
            ->select('practitioner_id')
            ->selectRaw('COUNT(*) as sessions_count, SUM(gross_amount) as handled_amount, SUM(amount) as earned_amount')
            ->with('practitioner:id,name')
            ->where('financial_week_id', $financialWeek->id)
            ->groupBy('practitioner_id')
            ->orderByDesc('sessions_count')
            ->limit(5)
            ->get();

        $recentTransactions = CashboxTransaction::query()
            ->with('cashbox:id,name')
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        return view('livewire.dashboard', compact(
            'todayStats',
            'upcomingAppointments',
            'operational',
            'financialWeek',
            'financial',
            'cashboxes',
            'trend',
            'topPractitioners',
            'recentTransactions',
        ) + ['mode' => 'admin']);
    }

    /** @return array<string, int|Collection> */
    private function practitionerStats(?int $practitionerId): array
    {
        if (! $practitionerId) {
            return ['week_sessions' => 0, 'week_earnings' => 0, 'paid' => 0, 'remaining' => 0, 'patients' => 0, 'week_schedule' => collect()];
        }

        $financialWeek = app(FinancialWeekService::class)->current();
        $weekStart = $financialWeek->starts_on->startOfDay();
        $weekEnd = $financialWeek->ends_on->endOfDay();

        return [
            'week_sessions' => TreatmentSession::query()->where('practitioner_id', $practitionerId)->whereBetween('started_at', [$weekStart, $weekEnd])->count(),
            'week_earnings' => PractitionerEarning::query()->where('practitioner_id', $practitionerId)->where('financial_week_id', $financialWeek->id)->sum('amount'),
            'paid' => PractitionerSettlement::query()->where('practitioner_id', $practitionerId)->sum('paid_amount'),
            'remaining' => PractitionerSettlement::query()->where('practitioner_id', $practitionerId)->latest('financial_week_id')->value('remaining_amount') ?? 0,
            'patients' => Patient::query()->whereHas('treatmentSessionParticipants.treatmentSession', fn (Builder $query) => $query->where('practitioner_id', $practitionerId))->count(),
            'week_schedule' => Appointment::query()
                ->with(['room:id,name', 'participants.patient:id,full_name'])
                ->where('practitioner_id', $practitionerId)
                ->whereBetween('starts_at', [$weekStart, $weekEnd])
                ->orderBy('starts_at')
                ->limit(20)
                ->get(),
        ];
    }
}
