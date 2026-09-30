<?php

namespace App\Livewire\Patients;

use App\Enums\ClinicNotificationType;
use App\Enums\PatientSource;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Services\ClinicNotificationService;
use App\Services\PatientFileNumberService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Patient $patient = null;

    public string $medicalFileNumber = '';

    public string $fullName = '';

    public string $age = '';

    public string $gender = '';

    public string $maritalStatus = '';

    public string $occupation = '';

    public string $companionName = '';

    public string $phone = '';

    public string $incomingHospitalName = '';

    public string $source = 'clinic';

    public ?int $referringPractitionerId = null;

    public ?int $currentPractitionerId = null;

    public bool $wasReferredOut = false;

    public string $outgoingHospitalName = '';

    public string $referredOutAt = '';

    public string $referralNotes = '';

    public string $administrativeNotes = '';

    public bool $isActive = true;

    public function mount(?Patient $patient = null): void
    {
        $this->patient = $patient?->exists ? $patient : null;
        Gate::authorize($this->patient ? 'update' : 'create', $this->patient ?? Patient::class);

        if ($this->patient) {
            $this->medicalFileNumber = $this->patient->medical_file_number;
            $this->fullName = $this->patient->full_name;
            $this->age = (string) ($this->patient->age ?? '');
            $this->gender = $this->patient->gender ?? '';
            $this->maritalStatus = $this->patient->marital_status ?? '';
            $this->occupation = $this->patient->occupation ?? '';
            $this->companionName = $this->patient->companion_name ?? '';
            $this->phone = $this->patient->phone ?? '';
            $this->incomingHospitalName = $this->patient->incoming_hospital_name ?? '';
            $this->source = $this->patient->source->value;
            $this->referringPractitionerId = $this->patient->referring_practitioner_id;
            $this->currentPractitionerId = $this->patient->current_practitioner_id;
            $this->wasReferredOut = $this->patient->was_referred_out;
            $this->outgoingHospitalName = $this->patient->outgoing_hospital_name ?? '';
            $this->referredOutAt = $this->patient->referred_out_at?->format('Y-m-d') ?? '';
            $this->referralNotes = $this->patient->referral_notes ?? '';
            $this->administrativeNotes = $this->patient->administrative_notes ?? '';
            $this->isActive = $this->patient->is_active;
        }
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function updatedSource(): void
    {
        if ($this->patient) {
            $this->source = $this->patient->source->value;
            $this->referringPractitionerId = $this->patient->referring_practitioner_id;

            return;
        }

        if ($this->source !== PatientSource::Practitioner->value) {
            $this->referringPractitionerId = null;
        }
    }

    public function updatedCurrentPractitionerId(?int $practitionerId): void
    {
        if ($this->patient) {
            return;
        }

        $this->medicalFileNumber = $practitionerId
            ? app(PatientFileNumberService::class)->nextForPractitioner($practitionerId)
            : '';

        $this->resetErrorBag('medicalFileNumber');
    }

    public function save(ClinicNotificationService $notifications): mixed
    {
        Gate::authorize($this->patient ? 'update' : 'create', $this->patient ?? Patient::class);

        if ($this->patient) {
            $this->source = $this->patient->source->value;
            $this->referringPractitionerId = $this->patient->referring_practitioner_id;
        }

        $validated = $this->validate([
            'medicalFileNumber' => [
                'required',
                'string',
                'max:64',
                Rule::when($this->patient === null, ['regex:/^[1-9][0-9]*$/']),
                Rule::unique('patients', 'medical_file_number')
                    ->where(fn ($query) => $query->where('current_practitioner_id', $this->currentPractitionerId))
                    ->ignore($this->patient?->id),
            ],
            'fullName' => ['required', 'string', 'max:255'],
            'age' => [Rule::requiredIf($this->patient === null), 'nullable', 'integer', 'min:0', 'max:130'],
            'gender' => [Rule::requiredIf($this->patient === null), 'nullable', Rule::in(['male', 'female'])],
            'maritalStatus' => [Rule::requiredIf($this->patient === null), 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'occupation' => ['nullable', 'string', 'max:255'],
            'companionName' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'incomingHospitalName' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::enum(PatientSource::class)],
            'referringPractitionerId' => [Rule::requiredIf($this->source === PatientSource::Practitioner->value), 'nullable', 'integer', 'exists:practitioners,id'],
            'currentPractitionerId' => [Rule::requiredIf($this->patient === null), 'nullable', 'integer', 'exists:practitioners,id'],
            'wasReferredOut' => ['boolean'],
            'outgoingHospitalName' => [Rule::requiredIf($this->wasReferredOut), 'nullable', 'string', 'max:255'],
            'referredOutAt' => ['nullable', 'date'],
            'referralNotes' => ['nullable', 'string', 'max:2000'],
            'administrativeNotes' => ['nullable', 'string', 'max:3000'],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'medical_file_number' => trim($validated['medicalFileNumber']),
            'full_name' => trim($validated['fullName']),
            'age' => filled($validated['age']) ? (int) $validated['age'] : null,
            'gender' => $validated['gender'] ?: null,
            'marital_status' => $validated['maritalStatus'] ?: null,
            'occupation' => filled($validated['occupation']) ? trim($validated['occupation']) : null,
            'companion_name' => filled($validated['companionName']) ? trim($validated['companionName']) : null,
            'phone' => filled($validated['phone']) ? trim($validated['phone']) : null,
            'incoming_hospital_name' => filled($validated['incomingHospitalName']) ? trim($validated['incomingHospitalName']) : null,
            'source' => $validated['source'],
            'referring_practitioner_id' => $validated['referringPractitionerId'],
            'current_practitioner_id' => $validated['currentPractitionerId'],
            'was_referred_out' => $validated['wasReferredOut'],
            'outgoing_hospital_name' => filled($validated['outgoingHospitalName']) ? trim($validated['outgoingHospitalName']) : null,
            'referred_out_at' => filled($validated['referredOutAt']) ? $validated['referredOutAt'] : null,
            'referral_notes' => filled($validated['referralNotes']) ? trim($validated['referralNotes']) : null,
            'administrative_notes' => filled($validated['administrativeNotes']) ? trim($validated['administrativeNotes']) : null,
            'is_active' => $validated['isActive'],
            'updated_by' => auth()->id(),
        ];

        if ($this->patient) {
            unset($attributes['source'], $attributes['referring_practitioner_id']);
            $this->patient->update($attributes);
        } else {
            $this->patient = Patient::query()->create($attributes + ['created_by' => auth()->id()]);
            $notifications->administrative(
                'patient:'.$this->patient->getKey().':created',
                ClinicNotificationType::PatientCreated,
                '👤 تم إنشاء ملف مريض جديد',
                "المريض: {$this->patient->full_name}\nرقم الملف: {$this->patient->medical_file_number}",
                route('patients.show', $this->patient),
                ['patient_id' => $this->patient->getKey()],
            );
        }

        session()->flash('success', 'تم حفظ ملف المريض بنجاح.');

        return $this->redirectRoute('patients.show', $this->patient, navigate: true);
    }

    public function render()
    {
        return view('livewire.patients.form')->title($this->patient ? 'تعديل ملف المريض' : 'مريض جديد');
    }
}
