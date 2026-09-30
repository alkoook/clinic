<?php

namespace App\Livewire\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Room;
use App\Services\AppointmentService;
use App\Services\TreatmentSessionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('المواعيد')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'من')]
    public string $dateFrom = '';

    #[Url(as: 'إلى')]
    public string $dateTo = '';

    #[Url(as: 'الطبيب')]
    public ?int $practitionerId = null;

    #[Url(as: 'الغرفة')]
    public ?int $roomId = null;

    #[Url(as: 'الحالة')]
    public string $status = '';

    #[Url(as: 'بحث')]
    public string $search = '';

    public string $cancellationReason = '';

    public ?int $cancellingId = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->can('appointments.manage') || $user->can('appointments.view_own'), 403);

        $this->dateFrom = $this->dateFrom ?: now()->startOfDay()->format('Y-m-d');
        $this->dateTo = $this->dateTo ?: now()->addDays(14)->format('Y-m-d');
        if ($user->hasRole('practitioner')) {
            $this->practitionerId = $user->practitioner?->id;
        }
    }

    #[Computed]
    public function appointments(): LengthAwarePaginator
    {
        $query = Appointment::query()->with([
            'practitioner:id,name',
            'room:id,name',
            'treatmentType:id,name',
            'participants.patient:id,full_name,medical_file_number',
            'treatmentSession:id,appointment_id,status',
        ]);

        if (auth()->user()->hasRole('practitioner')) {
            $query->where('practitioner_id', auth()->user()->practitioner?->id);
        }

        return $query
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->where('starts_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->where('starts_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->practitionerId, fn (Builder $query) => $query->where('practitioner_id', $this->practitionerId))
            ->when($this->roomId, fn (Builder $query) => $query->where('room_id', $this->roomId))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', fn (Builder $query) => $query->whereHas(
                'participants.patient',
                fn (Builder $patientQuery) => $patientQuery->where('full_name', 'like', '%'.trim($this->search).'%'),
            ))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['dateFrom', 'dateTo', 'practitionerId', 'roomId', 'status', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function openCancellation(int $appointmentId): void
    {
        Gate::authorize('appointments.manage');
        $this->cancellingId = $appointmentId;
        $this->cancellationReason = '';
    }

    public function cancel(AppointmentService $appointmentService): void
    {
        Gate::authorize('appointments.manage');
        $validated = $this->validate([
            'cancellingId' => ['required', 'integer', 'exists:appointments,id'],
            'cancellationReason' => ['required', 'string', 'max:1000'],
        ]);

        $appointment = Appointment::query()->findOrFail($validated['cancellingId']);
        $appointmentService->cancel($appointment, trim($validated['cancellationReason']), auth()->user());
        $this->reset(['cancellingId', 'cancellationReason']);
        $this->dispatch('app-toast', type: 'success', message: 'تم إلغاء الموعد دون أثر مالي.');
    }

    public function markStatus(int $appointmentId, string $status): void
    {
        Gate::authorize('appointments.manage');
        $newStatus = AppointmentStatus::tryFrom($status);
        abort_unless(in_array($newStatus, [AppointmentStatus::Attended, AppointmentStatus::NoShow], true), 422);

        if ($newStatus === AppointmentStatus::Attended) {
            Gate::authorize('sessions.manage');
            try {
                $session = app(TreatmentSessionService::class)->createFromAppointment(
                    Appointment::query()->findOrFail($appointmentId), [], auth()->user(),
                );
            } catch (SchedulingException $exception) {
                $this->addError('operation', $exception->getMessage());

                return;
            }
            $this->redirectRoute('sessions.show', $session, navigate: true);

            return;
        }

        DB::transaction(function () use ($appointmentId, $newStatus): void {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointmentId);
            abort_unless($appointment->status === AppointmentStatus::Scheduled, 422, 'لا يمكن تغيير حالة هذا الموعد.');
            $appointment->update(['status' => $newStatus]);
            $appointment->participants()->update(['status' => $newStatus->value]);
        });
    }

    public function render()
    {
        return view('livewire.appointments.index');
    }
}
