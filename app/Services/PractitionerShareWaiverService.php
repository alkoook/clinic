<?php

namespace App\Services;

use App\Exceptions\FinancialException;
use App\Models\Payment;
use App\Models\PractitionerEarning;
use App\Models\Refund;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class PractitionerShareWaiverService
{
    public function __construct(private readonly RefundService $refundService) {}

    public function waive(
        Payment $payment,
        int $amount,
        User $actor,
        string $idempotencyKey,
        ?string $reason = null,
        ?CarbonInterface $waivedAt = null,
    ): Refund {
        if (! $actor->hasPermission('refunds.create')) {
            throw FinancialException::permissionDenied('refunds.create');
        }

        return DB::transaction(function () use ($payment, $amount, $actor, $idempotencyKey, $reason, $waivedAt): Refund {
            $earning = PractitionerEarning::query()
                ->where('payment_id', $payment->getKey())
                ->lockForUpdate()->firstOrFail();

            if ($amount <= 0 || $amount > (int) $earning->getAttribute('amount')) {
                throw FinancialException::practitionerShareWaiverExceedsLiability();
            }

            return $this->refundService->refund(
                payment: $payment,
                amount: $amount,
                reason: $reason ?? 'تنازل المعالج عن جزء من مستحقاته',
                actor: $actor,
                idempotencyKey: $idempotencyKey,
                refundedAt: $waivedAt,
                reversePractitionerShareByRefundAmount: true,
            );
        }, 3);
    }
}
