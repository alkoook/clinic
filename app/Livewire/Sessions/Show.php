<?php

namespace App\Livewire\Sessions;

use App\Enums\AppointmentStatus;
use App\Exceptions\FinancialException;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Services\PatientAttachmentService;
use App\Services\PaymentService;
use App\Services\PractitionerShareWaiverService;
use App\Services\RefundService;
use App\Services\TreatmentSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    private const NEXT_APPOINTMENT_WARNING_MINUTES = 15;

    public TreatmentSession $treatmentSession;

    /** @var array<int|string, int|string> */
    public array $originalPrices = [];

    /** @var array<int|string, int|string> */
    public array $discountAmounts = [];

    /** @var array<int|string, string> */
    public array $paymentTokens = [];

    public ?int $refundingPaymentId = null;

    public int|string $refundAmount = 0;

    public string $refundReason = '';

    public string $refundToken = '';

    public ?int $waivingPaymentId = null;

    public int|string $waiverAmount = 0;

    public string $waiverToken = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachmentFiles = [];

    public ?int $attachmentPatientId = null;

    public function updatedAttachmentFiles(mixed $value): void
    {
        $this->attachmentFiles = PatientAttachmentService::readyUploads($value);
        $this->resetErrorBag(['attachmentFiles', 'attachmentFiles.*']);
    }

    public function updatedAttachmentPatientId(mixed $value): void
    {
        $this->attachmentPatientId = filled($value) ? (int) $value : null;
    }

    public function mount(TreatmentSession $treatmentSession): void
    {
        $user = auth()->user();
        abort_unless($user->can('sessions.manage') || ($user->can('sessions.view_own') && $user->practitioner?->id === $treatmentSession->practitioner_id), 403);
        $this->treatmentSession = $treatmentSession;
        $this->hydrateParticipantInputs();
        if ($this->treatmentSession->participants()->count() === 1) {
            $this->attachmentPatientId = (int) $this->treatmentSession->participants()->value('patient_id');
        }
    }

    public function pay(int $participantId, PaymentService $paymentService): void
    {
        Gate::authorize('payments.create');
        $participant = $this->treatmentSession->participants()->findOrFail($participantId);
        $validated = $this->validate([
            "originalPrices.$participantId" => ['required', 'integer', 'min:1'],
            "discountAmounts.$participantId" => ['required', 'integer', 'min:0'],
            "paymentTokens.$participantId" => ['required', 'uuid'],
        ]);

        $originalPrice = (int) $validated['originalPrices'][$participantId];
        $discountAmount = (int) $validated['discountAmounts'][$participantId];
        if ($discountAmount >= $originalPrice) {
            $this->addError("discountAmounts.$participantId", 'يجب أن يكون الخصم أقل من السعر الأصلي.');

            return;
        }

        try {
            $paymentService->pay(
                $participant,
                $originalPrice,
                $discountAmount,
                auth()->user(),
                $validated['paymentTokens'][$participantId],
            );
        } catch (FinancialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        $this->paymentTokens[$participantId] = (string) Str::uuid();
        $this->treatmentSession->refresh();
        $this->dispatch('app-toast', type: 'success', message: 'تم تسجيل الدفع وتثبيت الحصة المالية.');
    }

    public function prepareRefund(int $paymentId): void
    {
        Gate::authorize('refunds.create');
        $payment = Payment::query()
            ->whereHas('treatmentSessionParticipant', fn ($query) => $query->where('treatment_session_id', $this->treatmentSession->id))
            ->withSum('refunds as refunded_amount', 'amount')
            ->findOrFail($paymentId);
        $this->refundingPaymentId = $payment->id;
        $this->refundAmount = $payment->amount - (int) $payment->refunded_amount;
        $this->refundReason = '';
        $this->refundToken = (string) Str::uuid();
    }

    public function refund(RefundService $refundService): void
    {
        Gate::authorize('refunds.create');
        $validated = $this->validate([
            'refundingPaymentId' => ['required', 'integer', 'exists:payments,id'],
            'refundAmount' => ['required', 'integer', 'min:1'],
            'refundReason' => ['nullable', 'string', 'max:2000'],
            'refundToken' => ['required', 'uuid'],
        ]);

        $payment = Payment::query()
            ->whereHas('treatmentSessionParticipant', fn ($query) => $query->where('treatment_session_id', $this->treatmentSession->id))
            ->findOrFail($validated['refundingPaymentId']);

        try {
            $refundService->refund(
                $payment,
                (int) $validated['refundAmount'],
                filled($validated['refundReason']) ? trim($validated['refundReason']) : null,
                auth()->user(),
                $validated['refundToken'],
            );
        } catch (FinancialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        $this->reset(['refundingPaymentId', 'refundAmount', 'refundReason', 'refundToken']);
        $this->treatmentSession->refresh();
        $this->dispatch('app-toast', type: 'success', message: 'تم تنفيذ الاسترداد وتسجيل أثره المالي.');
    }

    public function prepareShareWaiver(int $paymentId): void
    {
        Gate::authorize('refunds.create');
        $payment = Payment::query()
            ->whereHas('treatmentSessionParticipant', fn ($query) => $query->where('treatment_session_id', $this->treatmentSession->id))
            ->with('practitionerEarning')
            ->findOrFail($paymentId);
        abort_if($payment->practitionerEarning?->amount <= 0, 422, 'لا توجد حصة مستحقة قابلة للإرجاع.');
        $this->waivingPaymentId = $payment->id;
        $this->waiverAmount = (int) $payment->practitionerEarning->amount;
        $this->waiverToken = (string) Str::uuid();
    }

    public function waivePractitionerShare(PractitionerShareWaiverService $waiverService): void
    {
        Gate::authorize('refunds.create');
        $validated = $this->validate([
            'waivingPaymentId' => ['required', 'integer', 'exists:payments,id'],
            'waiverAmount' => ['required', 'integer', 'min:1'],
            'waiverToken' => ['required', 'uuid'],
        ]);
        $payment = Payment::query()
            ->whereHas('treatmentSessionParticipant', fn ($query) => $query->where('treatment_session_id', $this->treatmentSession->id))
            ->findOrFail($validated['waivingPaymentId']);

        try {
            $waiverService->waive($payment, (int) $validated['waiverAmount'], auth()->user(), $validated['waiverToken']);
        } catch (FinancialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        $this->reset(['waivingPaymentId', 'waiverAmount', 'waiverToken']);
        $this->treatmentSession->refresh();
        $this->dispatch('app-toast', type: 'success', message: 'تم إرجاع حصة المعالج للمريض وتسجيل الأثر المالي.');
    }

    public function complete(TreatmentSessionService $treatmentSessionService): void
    {
        Gate::authorize('sessions.manage');
        try {
            $treatmentSessionService->complete($this->treatmentSession, auth()->user());
        } catch (SchedulingException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }
        $this->treatmentSession->refresh();
        $this->dispatch('app-toast', type: 'success', message: 'تم اعتماد اكتمال الجلسة.');
    }

    public function endTreatment(TreatmentSessionService $treatmentSessionService): void
    {
        Gate::authorize('sessions.manage');

        try {
            $treatmentSessionService->endTreatment(
                $this->treatmentSession,
                auth()->user(),
                CarbonImmutable::now(),
            );
        } catch (SchedulingException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        $this->treatmentSession->refresh();
        $this->dispatch('app-toast', type: 'success', message: 'تم تسجيل نهاية العلاج الفعلية وتحرير المعالج والغرفة.');
    }

    public function uploadAttachments(PatientAttachmentService $attachmentService): void
    {
        $this->attachmentFiles = PatientAttachmentService::readyUploads($this->attachmentFiles);
        $isSinglePatientSession = $this->treatmentSession->participants()->count() === 1;
        if ($isSinglePatientSession) {
            $this->attachmentPatientId = (int) $this->treatmentSession->participants()->value('patient_id');
        }

        $validated = $this->validate(
            [
                'attachmentPatientId' => [
                    $isSinglePatientSession ? 'nullable' : 'required',
                    'integer',
                    Rule::exists('treatment_session_participants', 'patient_id')
                        ->where('treatment_session_id', $this->treatmentSession->getKey()),
                ],
                ...PatientAttachmentService::livewireRules('attachmentFiles'),
            ],
            [
                'attachmentPatientId.required' => 'اختر المريض قبل رفع الملفات.',
                'attachmentPatientId.exists' => 'المريض المحدد ليس من المشاركين في هذه الجلسة.',
                ...PatientAttachmentService::livewireMessages('attachmentFiles'),
            ],
        );

        $participant = $this->treatmentSession->participants()->with('patient')->where('patient_id', $this->attachmentPatientId)->firstOrFail();
        Gate::authorize('uploadAttachment', $participant->patient);

        $storedAttachments = [];

        try {
            DB::transaction(function () use ($validated, $attachmentService, $participant, &$storedAttachments): void {
                foreach ($validated['attachmentFiles'] as $file) {
                    $storedAttachments[] = $attachmentService->store(
                        $file,
                        $participant->patient,
                        auth()->user(),
                        $this->treatmentSession,
                        errorKey: 'attachmentFiles',
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
            throw ValidationException::withMessages(['attachmentFiles' => 'تعذر رفع الملف. حاول مرة أخرى.']);
        }

        $this->reset(['attachmentFiles', 'attachmentPatientId']);
        $this->dispatch('app-toast', type: 'success', message: 'تم رفع الملفات وربطها بهذه الجلسة.');
    }

    #[Computed]
    public function previousPrices(): Collection
    {
        $patientIds = $this->treatmentSession->participants()->pluck('patient_id');
        $before = $this->treatmentSession->started_at->isPast() ? $this->treatmentSession->started_at : now();

        return TreatmentSessionParticipant::query()
            ->with(['treatmentSession.practitioner:id,name', 'treatmentSession.treatmentType:id,name'])
            ->whereIn('patient_id', $patientIds)
            ->where('treatment_session_id', '!=', $this->treatmentSession->getKey())
            ->where('original_price', '>', 0)
            ->whereHas('treatmentSession', fn ($query) => $query
                ->where('started_at', '<', $before)
                ->where('status', '!=', 'cancelled'))
            ->orderByDesc(
                TreatmentSession::query()->select('started_at')->whereColumn('treatment_sessions.id', 'treatment_session_participants.treatment_session_id'),
            )
            ->get()
            ->unique('patient_id')
            ->keyBy('patient_id');
    }

    public function usePreviousPrice(int $participantId): void
    {
        Gate::authorize('payments.create');
        $participant = $this->treatmentSession->participants()->findOrFail($participantId);
        $previous = $this->previousPrices->get($participant->patient_id);

        if ($previous === null) {
            $this->addError("originalPrices.$participantId", 'لا يوجد سعر سابق لهذا المريض.');

            return;
        }

        $this->originalPrices[$participantId] = $previous->original_price;
        $this->discountAmounts[$participantId] = 0;
    }

    #[Computed]
    public function canViewPractitionerShare(): bool
    {
        $user = auth()->user();

        return $user->can('financial.dashboard.view')
            || ($user->can('sessions.view_own') && $user->practitioner?->getKey() === $this->treatmentSession->practitioner_id);
    }

    #[Computed]
    public function nextAppointmentWarning(): ?Appointment
    {
        if ($this->treatmentSession->ended_at !== null) {
            return null;
        }

        return Appointment::query()
            ->with([
                'practitioner:id,name',
                'room:id,name',
                'participants.patient:id,full_name',
            ])
            ->whereKeyNot($this->treatmentSession->appointment_id)
            ->where('status', AppointmentStatus::Scheduled)
            ->where('starts_at', '>', $this->treatmentSession->started_at)
            ->where('starts_at', '<=', now()->addMinutes(self::NEXT_APPOINTMENT_WARNING_MINUTES))
            ->where(function ($query): void {
                $query->where('practitioner_id', $this->treatmentSession->practitioner_id)
                    ->orWhere('room_id', $this->treatmentSession->room_id);
            })
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();
    }

    public function render()
    {
        $this->treatmentSession->load([
            'appointment:id,starts_at,ends_at',
            'practitioner:id,name',
            'room:id,name',
            'treatmentType:id,name',
            'participants.patient:id,full_name,medical_file_number',
            'participants.payment.refunds',
            'participants.payment.practitionerEarning',
            'participants.payment.treatmentSessionParticipant',
        ]);

        return view('livewire.sessions.show')->title('تفاصيل الجلسة #'.$this->treatmentSession->id);
    }

    private function hydrateParticipantInputs(): void
    {
        $this->treatmentSession->load('participants');
        foreach ($this->treatmentSession->participants as $participant) {
            $this->originalPrices[$participant->id] = $participant->original_price;
            $this->discountAmounts[$participant->id] = $participant->discount_amount;
            $this->paymentTokens[$participant->id] = (string) Str::uuid();
        }
    }
}
