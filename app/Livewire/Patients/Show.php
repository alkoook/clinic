<?php

namespace App\Livewire\Patients;

use App\Models\Patient;
use App\Models\TreatmentSession;
use App\Services\PatientAttachmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Patient $patient;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    public string $attachmentTitle = '';

    public string $attachmentDescription = '';

    public ?int $treatmentSessionId = null;

    public function updatedFiles(mixed $value): void
    {
        $this->files = PatientAttachmentService::readyUploads($value);
        $this->resetErrorBag(['files', 'files.*']);
    }

    public function updatedTreatmentSessionId(mixed $value): void
    {
        $this->treatmentSessionId = filled($value) ? (int) $value : null;
    }

    public function removeFile(int $index): void
    {
        if (isset($this->files[$index])) {
            array_splice($this->files, $index, 1);
            $this->files = array_values($this->files);
        }
    }

    public function mount(Patient $patient): void
    {
        Gate::authorize('view', $patient);
        $this->patient = $patient;
    }

    #[Computed]
    public function appointments(): Collection
    {
        $practitionerId = $this->ownPractitionerId();

        return $this->patient->appointmentParticipants()
            ->with(['appointment.practitioner:id,name', 'appointment.room:id,name', 'appointment.treatmentType:id,name'])
            ->when($practitionerId, fn (Builder $query) => $query->whereHas('appointment', fn (Builder $appointmentQuery) => $appointmentQuery->where('practitioner_id', $practitionerId)))
            ->whereHas('appointment')
            ->latest('id')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function sessions(): Collection
    {
        $practitionerId = $this->ownPractitionerId();

        return $this->patient->treatmentSessionParticipants()
            ->with(['treatmentSession', 'payment.refunds'])
            ->when($practitionerId, fn (Builder $query) => $query->whereHas('treatmentSession', fn (Builder $sessionQuery) => $sessionQuery->where('practitioner_id', $practitionerId)))
            ->latest('id')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function attachments(): Collection
    {
        Gate::authorize('viewAttachments', $this->patient);

        return $this->patient->attachments()
            ->with('uploader:id,name')
            ->with('treatmentSession:id,practitioner_id,started_at', 'treatmentSession.practitioner:id,name')
            ->latest('uploaded_at')
            ->limit(50)
            ->get();
    }

    public function storeAttachments(PatientAttachmentService $attachmentService): void
    {
        Gate::authorize('uploadAttachment', $this->patient);

        $this->files = PatientAttachmentService::readyUploads($this->files);
        $this->treatmentSessionId = filled($this->treatmentSessionId) ? (int) $this->treatmentSessionId : null;
        $this->attachmentTitle = trim($this->attachmentTitle);
        $this->attachmentDescription = trim($this->attachmentDescription);

        $validated = $this->validate(
            [
                ...PatientAttachmentService::livewireRules('files'),
                'attachmentTitle' => ['nullable', 'string', 'max:255'],
                'attachmentDescription' => ['nullable', 'string', 'max:1000'],
                'treatmentSessionId' => ['nullable', 'integer', 'exists:treatment_sessions,id'],
            ],
            PatientAttachmentService::livewireMessages('files'),
        );

        if ($validated['treatmentSessionId'] !== null) {
            $practitionerId = $this->ownPractitionerId();
            $belongsToPatient = TreatmentSession::query()
                ->whereKey($validated['treatmentSessionId'])
                ->whereHas('participants', fn (Builder $query) => $query->where('patient_id', $this->patient->id))
                ->when($practitionerId, fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
                ->exists();

            if (! $belongsToPatient) {
                throw ValidationException::withMessages([
                    'treatmentSessionId' => 'لا يمكن ربط المرفق بجلسة لا تخص هذا المريض.',
                ]);
            }
        }

        $storedAttachments = [];

        try {
            DB::transaction(function () use ($validated, $attachmentService, &$storedAttachments): void {
                $session = filled($validated['treatmentSessionId'])
                    ? TreatmentSession::query()->findOrFail($validated['treatmentSessionId'])
                    : null;

                foreach ($validated['files'] as $file) {
                    $storedAttachments[] = $attachmentService->store(
                        $file,
                        $this->patient,
                        auth()->user(),
                        $session,
                        $validated['attachmentTitle'],
                        $validated['attachmentDescription'],
                    );
                }
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);
            throw ValidationException::withMessages(['files' => 'تعذر رفع الملف. حاول مرة أخرى.']);
        }

        $this->reset(['files', 'attachmentTitle', 'attachmentDescription', 'treatmentSessionId']);
        unset($this->attachments);
        $this->dispatch('app-toast', type: 'success', message: 'تم رفع المرفقات إلى الملف الآمن.');
    }

    public function render()
    {
        return view('livewire.patients.show')->title('ملف '.$this->patient->full_name);
    }

    private function ownPractitionerId(): ?int
    {
        $user = auth()->user();

        if ($user->can('patients.view')) {
            return null;
        }

        $practitionerId = $user->practitioner?->id;
        abort_unless($user->can('patients.view_own') && $practitionerId !== null, 403);

        return $practitionerId;
    }
}
