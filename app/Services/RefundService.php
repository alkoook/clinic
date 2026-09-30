<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\FinancialAdjustmentType;
use App\Enums\FinancialWeekStatus;
use App\Enums\ParticipantPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\FinancialAdjustment;
use App\Models\FinancialWeek;
use App\Models\Payment;
use App\Models\PractitionerEarning;
use App\Models\Refund;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class RefundService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly CashboxService $cashboxService,
        private readonly PaymentService $paymentService,
        private readonly FinancialAuditService $auditService,
        private readonly TreatmentSessionService $treatmentSessionService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function refund(
        Payment $payment,
        int $amount,
        ?string $reason,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $refundedAt = null,
        bool $reversePractitionerShareByRefundAmount = false,
    ): Refund {
        if (! $actor->hasPermission('refunds.create')) {
            throw FinancialException::permissionDenied('refunds.create');
        }

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);
        $this->cashboxService->assertPositiveAmount($amount);

        $reason = filled($reason) ? trim((string) $reason) : null;

        try {
            return DB::transaction(function () use (
                $payment,
                $amount,
                $reason,
                $actor,
                $idempotencyKey,
                $refundedAt,
                $reversePractitionerShareByRefundAmount,
            ): Refund {
                /** @var Refund|null $idempotentRefund */
                $idempotentRefund = Refund::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($idempotentRefund !== null) {
                    $this->assertMatchingRefund($idempotentRefund, $payment, $amount, $reason);

                    return $idempotentRefund;
                }

                /** @var Payment $lockedPayment */
                $lockedPayment = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($this->enumValue($lockedPayment->getAttribute('status')) === PaymentStatus::Voided->value) {
                    throw FinancialException::refundExceedsPayment();
                }

                $priorRefunds = Refund::query()
                    ->where('payment_id', $lockedPayment->getKey())
                    ->where('status', RefundStatus::Completed->value)
                    ->lockForUpdate()
                    ->get();
                $alreadyRefunded = (int) $priorRefunds->sum(
                    static fn (Refund $refund): int => (int) $refund->getAttribute('amount'),
                );
                $paymentAmount = (int) $lockedPayment->getAttribute('amount');

                if ($amount > $paymentAmount - $alreadyRefunded) {
                    throw FinancialException::refundExceedsPayment();
                }

                /** @var TreatmentSessionParticipant $participant */
                $participant = TreatmentSessionParticipant::query()
                    ->whereKey($lockedPayment->getAttribute('treatment_session_participant_id'))
                    ->lockForUpdate()
                    ->firstOrFail();
                /** @var PractitionerEarning $earning */
                $earning = PractitionerEarning::query()
                    ->where('payment_id', $lockedPayment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                /** @var FinancialWeek $originalWeek */
                $originalWeek = FinancialWeek::query()
                    ->whereKey($lockedPayment->getAttribute('financial_week_id'))
                    ->lockForUpdate()
                    ->firstOrFail();
                $date = $refundedAt ?? now();
                $currentWeek = $this->financialWeekService->forDate($date, true);
                $this->financialWeekService->assertOpen($currentWeek);
                $mainCashbox = $this->cashboxService->main(true);
                $cashboxTransaction = $this->cashboxService->record(
                    cashbox: $mainCashbox,
                    financialWeek: $currentWeek,
                    type: TransactionType::Refund,
                    direction: TransactionDirection::Out,
                    amount: $amount,
                    occurredAt: $date,
                    reference: null,
                    description: $reason,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                );
                $afterClosedWeek = $this->enumValue($originalWeek->getAttribute('status')) === FinancialWeekStatus::Closed->value;
                $requiresAdjustment = $afterClosedWeek || ! $currentWeek->is($originalWeek);

                $refund = new Refund;
                $refund->forceFill([
                    'payment_id' => $lockedPayment->getKey(),
                    'financial_week_id' => $currentWeek->getKey(),
                    'cashbox_transaction_id' => $cashboxTransaction->getKey(),
                    'amount' => $amount,
                    'reason' => $reason,
                    'status' => RefundStatus::Completed->value,
                    'after_closed_week' => $afterClosedWeek,
                    'refunded_at' => $date,
                    'idempotency_key' => $idempotencyKey,
                    'refunded_by' => $actor->getKey(),
                ]);
                $refund->save();
                $this->cashboxService->finalizeReference($cashboxTransaction, $refund);

                $cumulativeRefund = $alreadyRefunded + $amount;
                $originalPractitionerShare = (int) $participant->getAttribute('practitioner_share');
                $targetReversedShare = $reversePractitionerShareByRefundAmount
                    ? min($originalPractitionerShare, $cumulativeRefund)
                    : ($cumulativeRefund === $paymentAmount
                    ? $originalPractitionerShare
                    : min(
                        $originalPractitionerShare,
                        $this->paymentService->calculatePractitionerShare(
                            $cumulativeRefund,
                            (string) $participant->getAttribute('practitioner_percentage'),
                        ),
                    ));
                $adjustment = null;

                if ($requiresAdjustment) {
                    $priorAdjustmentTotal = $priorRefunds->isEmpty()
                        ? 0
                        : (int) FinancialAdjustment::query()
                            ->whereIn('refund_id', $priorRefunds->pluck('id'))
                            ->where('type', FinancialAdjustmentType::PractitionerRefund->value)
                            ->sum('amount');
                    $alreadyReversedShare = ($originalPractitionerShare - (int) $earning->getAttribute('amount'))
                        + max(0, -$priorAdjustmentTotal);
                    $shareToReverse = max(0, $targetReversedShare - $alreadyReversedShare);

                    if ($shareToReverse > 0) {
                        $adjustment = new FinancialAdjustment;
                        $adjustment->forceFill([
                            'practitioner_id' => $earning->getAttribute('practitioner_id'),
                            'financial_week_id' => $currentWeek->getKey(),
                            'refund_id' => $refund->getKey(),
                            'type' => FinancialAdjustmentType::PractitionerRefund->value,
                            'amount' => -$shareToReverse,
                            'reason' => 'Practitioner share reversal for refund #'.$refund->getKey(),
                            'occurred_at' => $date,
                            'created_by' => $actor->getKey(),
                        ]);
                        $adjustment->save();
                        $this->auditService->record(
                            'financial_adjustment.created',
                            $adjustment,
                            $actor,
                            after: $adjustment->getAttributes(),
                        );
                    }
                } else {
                    $remainingShare = max(0, $originalPractitionerShare - $targetReversedShare);
                    $earning->forceFill([
                        'amount' => $remainingShare,
                        'status' => $cumulativeRefund === $paymentAmount ? 'refunded' : 'partially_refunded',
                    ]);
                    $earning->save();
                }

                $isFullyRefunded = $cumulativeRefund === $paymentAmount;
                $lockedPayment->forceFill([
                    'status' => $isFullyRefunded
                        ? PaymentStatus::Refunded->value
                        : PaymentStatus::PartiallyRefunded->value,
                ]);
                $lockedPayment->save();
                $participant->forceFill([
                    'payment_status' => $isFullyRefunded
                        ? ParticipantPaymentStatus::Refunded->value
                        : ParticipantPaymentStatus::PartiallyRefunded->value,
                ]);
                $participant->save();

                /** @var TreatmentSession $session */
                $session = TreatmentSession::query()->findOrFail((int) $participant->getAttribute('treatment_session_id'));
                $this->treatmentSessionService->synchronizePaymentStatus($session);
                $this->auditService->record(
                    'refund.created',
                    $refund,
                    $actor,
                    after: [
                        ...$refund->getAttributes(),
                        'financial_adjustment_id' => $adjustment?->getKey(),
                    ],
                );
                $session->loadMissing(['practitioner:id,user_id,name,telegram_chat_id', 'treatmentType:id,name']);
                $this->notifications->financial(
                    'refund:'.$refund->getKey().':completed',
                    ClinicNotificationType::PaymentRefunded,
                    '↩️ تم تسجيل استرداد',
                    "المريض: {$participant->patient_name}\n"
                        ."العلاج: {$session->treatmentType?->name}\n"
                        .'المبلغ: '.number_format($amount)." ل.س\n"
                        .'التاريخ: '.$date->format('Y/m/d H:i'),
                    route('sessions.show', $session),
                    [
                        'refund_id' => $refund->getKey(),
                        'payment_id' => $lockedPayment->getKey(),
                        'amount' => $amount,
                    ],
                    $session->practitioner,
                    '↩️ استرداد مرتبط بإحدى جلساتك',
                    "المريض: {$participant->patient_name}\n"
                        ."العلاج: {$session->treatmentType?->name}\n"
                        .'المبلغ المسترد: '.number_format($amount).' ل.س',
                );

                if ($adjustment !== null) {
                    $this->notifications->financial(
                        'financial-adjustment:'.$adjustment->getKey().':created',
                        ClinicNotificationType::PractitionerAdjustmentCreated,
                        '🧮 تم إنشاء تعديل مالي للمعالج',
                        "المعالج: {$session->practitioner?->name}\n"
                            .'قيمة التعديل: '.number_format((int) $adjustment->amount).' ل.س',
                        route('finance.audit-log'),
                        ['financial_adjustment_id' => $adjustment->getKey()],
                        $session->practitioner,
                        '🧮 تعديل على مستحقاتك',
                        'قيمة التعديل: '.number_format((int) $adjustment->amount).' ل.س',
                    );
                }

                return $refund->refresh();
            }, 3);
        } catch (QueryException $exception) {
            /** @var Refund|null $existing */
            $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingRefund($existing, $payment, $amount, $reason);

            return $existing;
        }
    }

    private function assertMatchingRefund(Refund $refund, Payment $payment, int $amount, ?string $reason): void
    {
        if ((int) $refund->getAttribute('payment_id') !== (int) $payment->getKey()
            || (int) $refund->getAttribute('amount') !== $amount
            || $refund->getAttribute('reason') !== $reason) {
            throw FinancialException::duplicateOperation();
        }
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
