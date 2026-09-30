<?php

namespace App\Livewire\Sessions;

use App\Enums\AppointmentStatus;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\TreatmentSession;
use App\Services\TreatmentSessionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('الجلسات')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'من')]
    public string $dateFrom = '';

    #[Url(as: 'إلى')]
    public string $dateTo = '';

    #[Url(as: 'الحالة')]
    public string $status = '';

    public ?int $startingAppointmentId = null;

    /** @var array<int|string, array{original_price: int|string, discount_amount: int|string}> */
    public array $prices = [];

    /** @var array<int> */
    public array $attendingParticipantIds = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->can('sessions.manage') || $user->can('sessions.view_own'), 403);
        $this->dateFrom = $this->dateFrom ?: now()->startOfWeek()->format('Y-m-d');
        $this->dateTo = $this->dateTo ?: now()->endOfWeek()->format('Y-m-d');
    }

    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        $user = auth()->user();
        $practitionerId = null;

        if (! $user->can('sessions.manage')) {
            $practitionerId = $user->practitioner?->getKey();
            abort_unless($user->can('sessions.view_own') && $practitionerId !== null, 403);
        }

        return TreatmentSession::query()
            ->with(['practitioner:id,name', 'room:id,name', 'treatmentType:id,name', 'participants:id,treatment_session_id,patient_id', 'participants.patient:id,full_name'])
            ->withSum('participants', 'final_amount')
            ->when($this->canViewPractitionerShare, fn (Builder $query) => $query->withSum('participants', 'practitioner_share'))
            ->when($practitionerId !== null, fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->where('started_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->where('started_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function canViewPractitionerShare(): bool
    {
        $user = auth()->user();

        return $user->can('financial.dashboard.view')
            || ($user->can('sessions.view_own') && $user->practitioner?->getKey() !== null);
    }

    #[Computed]
    public function readyAppointments(): Collection
    {
        if (! auth()->user()->can('sessions.manage')) {
            return new Collection;
        }

        return Appointment::query()
            ->with(['practitioner:id,name', 'room:id,name', 'treatmentType:id,name', 'participants.patient:id,full_name'])
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Attended])
            ->whereDoesntHave('treatmentSession')
            ->whereBetween('starts_at', [now()->subDays(7)->startOfDay(), now()])
            ->orderBy('starts_at')
            ->limit(30)
            ->get();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['dateFrom', 'dateTo', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function prepareSession(int $appointmentId): void
    {
        Gate::authorize('sessions.manage');
        $appointment = Appointment::query()->with('participants')->whereDoesntHave('treatmentSession')->findOrFail($appointmentId);
        $this->startingAppointmentId = $appointment->id;
        $this->attendingParticipantIds = $appointment->participants
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $this->prices = $appointment->participants->mapWithKeys(fn ($participant) => [
            $participant->id => ['original_price' => 0, 'discount_amount' => 0],
        ])->all();
    }

    public function createSession(TreatmentSessionService $treatmentSessionService): mixed
    {
        Gate::authorize('sessions.manage');
        $rules = [
            'startingAppointmentId' => ['required', 'integer', 'exists:appointments,id'],
            'prices' => ['required', 'array', 'min:1'],
            'attendingParticipantIds' => ['required', 'array', 'min:1'],
            'attendingParticipantIds.*' => ['required', 'integer'],
        ];

        foreach ($this->attendingParticipantIds as $participantId) {
            $rules["prices.$participantId.original_price"] = ['required', 'integer', 'min:1'];
            $rules["prices.$participantId.discount_amount"] = ['required', 'integer', 'min:0'];
        }

        $validated = $this->validate($rules);

        foreach ($validated['prices'] as $participantId => $price) {
            if (! in_array((int) $participantId, $validated['attendingParticipantIds'], true)) {
                continue;
            }

            if ((int) $price['discount_amount'] >= (int) $price['original_price']) {
                $this->addError("prices.$participantId.discount_amount", 'يجب أن يكون الخصم أقل من السعر الأصلي.');

                return null;
            }
        }

        $appointment = Appointment::query()->findOrFail($validated['startingAppointmentId']);

        try {
            $session = $treatmentSessionService->createFromAppointment(
                $appointment,
                $validated['prices'],
                auth()->user(),
                $validated['attendingParticipantIds'],
            );
        } catch (SchedulingException $exception) {
            $this->addError('operation', $exception->getMessage());

            return null;
        }

        $this->reset(['startingAppointmentId', 'prices', 'attendingParticipantIds']);

        return $this->redirectRoute('sessions.show', $session, navigate: true);
    }

    public function render()
    {
        return view('livewire.sessions.index');
    }
}
