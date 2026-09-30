<?php

namespace App\Livewire\Appointments;

use App\Enums\AppointmentKind;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Room;
use App\Models\TreatmentType;
use App\Services\AppointmentService;
use App\Services\AvailabilityService;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Appointment $appointment = null;

    public ?int $practitionerId = null;

    public ?int $roomId = null;

    public ?int $treatmentTypeId = null;

    public string $date = '';

    public string $startTime = '';

    public int $durationMinutes = 60;

    public string $kind = 'individual';

    /** @var array<int, int|string> */
    public array $patientIds = [];

    public string $patientSearch = '';

    public string $notes = '';

    public function mount(?Appointment $appointment = null): void
    {
        Gate::authorize('appointments.manage');
        $this->appointment = $appointment?->exists ? $appointment->load('participants') : null;

        if ($this->appointment) {
            $this->practitionerId = $this->appointment->practitioner_id;
            $this->roomId = $this->appointment->room_id;
            $this->treatmentTypeId = $this->appointment->treatment_type_id;
            $this->date = $this->appointment->starts_at->format('Y-m-d');
            $this->startTime = $this->appointment->starts_at->format('H:i');
            $this->durationMinutes = $this->appointment->duration_minutes;
            $this->kind = $this->appointment->kind->value;
            $this->patientIds = $this->appointment->participants->pluck('patient_id')->all();
            $this->notes = $this->appointment->notes ?? '';
        } else {
            $this->durationMinutes = app(SettingsService::class)->integer('appointment.default_duration', 60);
            $this->date = now()->format('Y-m-d');
            $this->startTime = now()->addMinutes(30)->format('H:i');
        }
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

    #[Computed]
    public function treatmentTypes(): Collection
    {
        return TreatmentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'is_group']);
    }

    #[Computed]
    public function patientOptions(): Collection
    {
        return Patient::query()
            ->where('is_active', true)
            ->when($this->patientSearch !== '', function ($query): void {
                $term = trim($this->patientSearch);
                $query->where(fn ($nested) => $nested
                    ->where('medical_file_number', $term)
                    ->orWhere('phone', $term)
                    ->orWhere('full_name', 'like', $term.'%'));
            }, fn ($query) => $query->whereIn('id', array_map('intval', $this->patientIds ?: [0])))
            ->orderBy('full_name')
            ->limit(20)
            ->get(['id', 'full_name', 'medical_file_number', 'phone']);
    }

    public function updatedTreatmentTypeId(?int $value): void
    {
        $treatmentType = $value ? TreatmentType::query()->find($value) : null;
        if ($treatmentType) {
            $this->kind = $treatmentType->is_group ? AppointmentKind::Group->value : AppointmentKind::Individual->value;
        }
    }

    /** @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}> */
    #[Computed]
    public function availablePeriods(): array
    {
        if (! $this->practitionerId || ! $this->roomId || ! Validator::make(['date' => $this->date], ['date' => ['required', 'date_format:Y-m-d']])->passes()) {
            return [];
        }

        return array_values(array_filter(
            app(AvailabilityService::class)->bookingIntervals($this->practitionerId, $this->date, $this->roomId, $this->appointment?->id),
            fn (array $period): bool => $period['start']->diffInMinutes($period['end']) >= $this->durationMinutes,
        ));
    }

    public function save(AppointmentService $appointmentService): mixed
    {
        Gate::authorize('appointments.manage');

        $validated = $this->validate([
            'practitionerId' => ['required', 'integer', Rule::exists('practitioners', 'id')->where('is_active', true)],
            'roomId' => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'treatmentTypeId' => ['required', 'integer', Rule::exists('treatment_types', 'id')->where('is_active', true)],
            'date' => ['required', 'date'],
            'startTime' => ['required', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'min:5', 'max:720'],
            'kind' => ['required', Rule::enum(AppointmentKind::class)],
            'patientIds' => ['required', 'array', 'min:1'],
            'patientIds.*' => ['integer', 'distinct', Rule::exists('patients', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['kind'] === AppointmentKind::Individual->value && count($validated['patientIds']) !== 1) {
            $this->addError('patientIds', 'الجلسة الفردية تتطلب مريضًا واحدًا فقط.');

            return null;
        }

        $data = [
            'practitioner_id' => $validated['practitionerId'],
            'room_id' => $validated['roomId'],
            'treatment_type_id' => $validated['treatmentTypeId'],
            'starts_at' => $validated['date'].' '.$validated['startTime'],
            'duration_minutes' => $validated['durationMinutes'],
            'kind' => $validated['kind'],
            'patient_ids' => array_map('intval', $validated['patientIds']),
            'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
        ];

        try {
            $appointment = $this->appointment
                ? $appointmentService->reschedule($this->appointment, $data, auth()->user())
                : $appointmentService->book($data, auth()->user());
        } catch (SchedulingException $exception) {
            $this->addError('schedule', $exception->getMessage());

            return null;
        }

        session()->flash('success', $this->appointment ? 'تمت إعادة جدولة الموعد مع حفظ سجله السابق.' : 'تم حجز الموعد بنجاح.');

        return $this->redirectRoute('appointments.index', ['highlight' => $appointment->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.appointments.form')->title($this->appointment ? 'إعادة جدولة الموعد' : 'حجز موعد');
    }
}
