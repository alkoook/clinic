<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\ParticipantPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\FinancialAgreement;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly FinancialAgreementService $agreementService,
        private readonly CashboxService $cashboxService,
        private readonly FinancialAuditService $auditService,
        private readonly TreatmentSessionService $treatmentSessionService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function pay(
        TreatmentSessionParticipant $participant,
        int $originalPrice,
        int $discountAmount,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $paidAt = null,
    ): Payment {
        if (! $actor->hasPermission('payments.create')) {
            throw FinancialException::permissionDenied('payments.create');
        }

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);
        $finalAmount = $this->finalAmount($originalPrice, $discountAmount);

        try {
            return DB::transaction(function () use (
                $participant,
                $originalPrice,
                $discountAmount,
                $finalAmount,
                $actor,
                $idempotencyKey,
                $paidAt,
            ): Payment {
                /** @var Payment|null $idempotentPayment */
                $idempotentPayment = Payment::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($idempotentPayment !== null) {
                    $this->assertMatchingPayment(
                        $idempotentPayment,
                        $participant,
                        $originalPrice,
                        $discountAmount,
                        $finalAmount,
                    );

                    return $idempotentPayment;
                }

                /** @var TreatmentSessionParticipant $lockedParticipant */
                $lockedParticipant = TreatmentSessionParticipant::query()
                    ->whereKey($participant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var Payment|null $existingPayment */
                $existingPayment = Payment::query()
                    ->where('treatment_session_participant_id', $lockedParticipant->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existingPayment !== null) {
                    throw FinancialException::alreadyPaid();
                }

                $existingOriginalPrice = (int) $lockedParticipant->getAttribute('original_price');

                if ($existingOriginalPrice > 0 && $existingOriginalPrice !== $originalPrice) {
                    throw FinancialException::invalidAmount('لا يطابق السعر القيمة المحفوظة للجلسة.');
                }

                if ($discountAmount > 0 && ! $actor->hasPermission('discounts.apply')) {
                    throw FinancialException::discountNotAuthorized();
                }

                /** @var TreatmentSession $session */
                $session = TreatmentSession::query()
                    ->whereKey($lockedParticipant->getAttribute('treatment_session_id'))
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($this->enumValue($session->getAttribute('status')), ['completed', 'refunded', 'cancelled'], true)) {
                    throw FinancialException::alreadyPaid();
                }

                $paymentDate = $paidAt ?? now();
                $financialWeek = $this->financialWeekService->forDate($paymentDate, true);
                $this->financialWeekService->assertOpen($financialWeek);
                Practitioner::query()
                    ->whereKey($session->getAttribute('practitioner_id'))
                    ->lockForUpdate()
                    ->firstOrFail();
                $agreement = $this->agreementService->effectiveFor(
                    (int) $session->getAttribute('practitioner_id'),
                    $session->getAttribute('started_at'),
                );
                /** @var FinancialAgreement $lockedAgreement */
                $lockedAgreement = FinancialAgreement::query()
                    ->whereKey($agreement->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $percentage = (string) $lockedAgreement->getAttribute('practitioner_percentage');
                $practitionerShare = $this->calculatePractitionerShare($finalAmount, $percentage);
                $clinicShare = $finalAmount - $practitionerShare;
                $mainCashbox = $this->cashboxService->main(true);
                $cashboxTransaction = $this->cashboxService->record(
                    cashbox: $mainCashbox,
                    financialWeek: $financialWeek,
                    type: TransactionType::SessionPayment,
                    direction: TransactionDirection::In,
                    amount: $finalAmount,
                    occurredAt: $paymentDate,
                    reference: null,
                    description: 'دفعة جلسة علاجية',
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                );

                $lockedParticipant->forceFill([
                    'original_price' => $originalPrice,
                    'discount_amount' => $discountAmount,
                    'final_amount' => $finalAmount,
                    'practitioner_percentage' => $this->formatPercentage($percentage),
                    'practitioner_share' => $practitionerShare,
                    'clinic_share' => $clinicShare,
                    'financial_agreement_id' => $lockedAgreement->getKey(),
                    'payment_status' => ParticipantPaymentStatus::Paid->value,
                    'discount_approved_by' => $discountAmount > 0 ? $actor->getKey() : null,
                ]);
                $lockedParticipant->save();

                $payment = new Payment;
                $payment->forceFill([
                    'treatment_session_participant_id' => $lockedParticipant->getKey(),
                    'financial_week_id' => $financialWeek->getKey(),
                    'cashbox_transaction_id' => $cashboxTransaction->getKey(),
                    'amount' => $finalAmount,
                    'method' => PaymentMethod::Cash->value,
                    'status' => PaymentStatus::Paid->value,
                    'paid_at' => $paymentDate,
                    'idempotency_key' => $idempotencyKey,
                    'received_by' => $actor->getKey(),
                ]);
                $payment->save();
                $this->cashboxService->finalizeReference($cashboxTransaction, $payment);

                $earning = new PractitionerEarning;
                $earning->forceFill([
                    'payment_id' => $payment->getKey(),
                    'treatment_session_participant_id' => $lockedParticipant->getKey(),
                    'practitioner_id' => $session->getAttribute('practitioner_id'),
                    'financial_week_id' => $financialWeek->getKey(),
                    'gross_amount' => $finalAmount,
                    'practitioner_percentage' => $this->formatPercentage($percentage),
                    'amount' => $practitionerShare,
                    'status' => 'accrued',
                    'earned_at' => $paymentDate,
                ]);
                $earning->save();

                $this->treatmentSessionService->synchronizePaymentStatus($session);
                $this->auditService->record(
                    'payment.created',
                    $payment,
                    $actor,
                    after: [
                        ...$payment->getAttributes(),
                        'original_price' => $originalPrice,
                        'discount_amount' => $discountAmount,
                        'practitioner_percentage' => $this->formatPercentage($percentage),
                        'practitioner_share' => $practitionerShare,
                        'clinic_share' => $clinicShare,
                    ],
                );
                $session->loadMissing(['practitioner:id,user_id,name,telegram_chat_id', 'treatmentType:id,name']);
                $this->notifications->financial(
                    'payment:'.$payment->getKey().':received',
                    ClinicNotificationType::PaymentReceived,
                    '💰 تم تسجيل دفعة',
                    "المريض: {$lockedParticipant->patient_name}\n"
                        ."الجلسة: {$session->treatmentType?->name}\n"
                        .'المبلغ: '.number_format($finalAmount)." ل.س\n"
                        .'التاريخ: '.$paymentDate->format('Y/m/d H:i'),
                    route('sessions.show', $session),
                    [
                        'payment_id' => $payment->getKey(),
                        'treatment_session_id' => $session->getKey(),
                        'amount' => $finalAmount,
                    ],
                    $session->practitioner,
                    '💰 تم تسجيل دفعة لك',
                    "المريض: {$lockedParticipant->patient_name}\n"
                        ."العلاج: {$session->treatmentType?->name}\n"
                        .'المبلغ الإجمالي: '.number_format($finalAmount).' ل.س',
                );

                return $payment->refresh();
            }, 3);
        } catch (QueryException $exception) {
            /** @var Payment|null $existing */
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingPayment($existing, $participant, $originalPrice, $discountAmount, $finalAmount);

            return $existing;
        }
    }

    public function calculatePractitionerShare(int $amount, int|string $percentage): int
    {
        if ($amount < 0) {
            throw FinancialException::invalidAmount();
        }

        $basisPoints = $this->percentageBasisPoints($percentage);
        $wholeUnits = intdiv($amount, 10_000);
        $remainder = $amount % 10_000;

        return ($wholeUnits * $basisPoints)
            + intdiv(($remainder * $basisPoints) + 5_000, 10_000);
    }

    private function finalAmount(int $originalPrice, int $discountAmount): int
    {
        if ($originalPrice <= 0 || $discountAmount < 0 || $discountAmount >= $originalPrice) {
            throw FinancialException::invalidAmount('يجب أن يكون السعر موجبًا وأن يكون الخصم المصرح به أقل من السعر.');
        }

        return $originalPrice - $discountAmount;
    }

    private function percentageBasisPoints(int|string $percentage): int
    {
        $normalized = trim((string) $percentage);

        if (! preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $normalized)) {
            throw FinancialException::invalidPercentage();
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $basisPoints = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        if ($basisPoints > 10_000) {
            throw FinancialException::invalidPercentage();
        }

        return $basisPoints;
    }

    private function formatPercentage(int|string $percentage): string
    {
        $basisPoints = $this->percentageBasisPoints($percentage);

        return sprintf('%d.%02d', intdiv($basisPoints, 100), $basisPoints % 100);
    }

    private function assertMatchingPayment(
        Payment $payment,
        TreatmentSessionParticipant $participant,
        int $originalPrice,
        int $discountAmount,
        int $amount,
    ): void {
        /** @var TreatmentSessionParticipant|null $snapshot */
        $snapshot = TreatmentSessionParticipant::query()
            ->find($payment->getAttribute('treatment_session_participant_id'));
        $matches = (int) $payment->getAttribute('treatment_session_participant_id') === (int) $participant->getKey()
            && (int) $payment->getAttribute('amount') === $amount
            && (int) $snapshot?->getAttribute('original_price') === $originalPrice
            && (int) $snapshot?->getAttribute('discount_amount') === $discountAmount;

        if (! $matches) {
            throw FinancialException::duplicateOperation();
        }
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
