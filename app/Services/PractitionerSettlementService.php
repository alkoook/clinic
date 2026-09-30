<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\SettlementStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\FinancialAdjustment;
use App\Models\FinancialWeek;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\PractitionerSettlement;
use App\Models\PractitionerSettlementPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PractitionerSettlementService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly CashboxService $cashboxService,
        private readonly FinancialAuditService $auditService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function pay(
        PractitionerSettlement $settlement,
        int $amount,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $paidAt = null,
    ): PractitionerSettlementPayment {
        if (! $actor->hasPermission('settlements.manage')) {
            throw FinancialException::permissionDenied('settlements.manage');
        }

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);
        $this->cashboxService->assertPositiveAmount($amount);

        try {
            return DB::transaction(function () use (
                $settlement,
                $amount,
                $actor,
                $idempotencyKey,
                $paidAt,
            ): PractitionerSettlementPayment {
                /** @var PractitionerSettlementPayment|null $idempotentPayment */
                $idempotentPayment = PractitionerSettlementPayment::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($idempotentPayment !== null) {
                    $this->assertMatchingPayment($idempotentPayment, $settlement, $amount);

                    return $idempotentPayment;
                }

                $date = $paidAt ?? now();
                $movementWeek = $this->financialWeekService->forDate($date, true);

                /** @var PractitionerSettlement $lockedSettlement */
                $lockedSettlement = PractitionerSettlement::query()
                    ->whereKey($settlement->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                /** @var FinancialWeek $settlementWeek */
                $settlementWeek = FinancialWeek::query()
                    ->whereKey($lockedSettlement->getAttribute('financial_week_id'))
                    ->firstOrFail();
                $hasLaterSettlement = PractitionerSettlement::query()
                    ->join('financial_weeks', 'financial_weeks.id', '=', 'practitioner_settlements.financial_week_id')
                    ->where('practitioner_settlements.practitioner_id', $lockedSettlement->getAttribute('practitioner_id'))
                    ->whereDate('financial_weeks.starts_on', '>', $settlementWeek->getAttribute('starts_on'))
                    ->exists();
                $remainingAmount = (int) $lockedSettlement->getAttribute('remaining_amount');

                if ($hasLaterSettlement || $remainingAmount <= 0 || $amount > $remainingAmount) {
                    throw FinancialException::settlementNotPayable();
                }

                $mainCashbox = $this->cashboxService->main(true);
                $transaction = $this->cashboxService->record(
                    cashbox: $mainCashbox,
                    financialWeek: $movementWeek,
                    type: TransactionType::PractitionerSettlement,
                    direction: TransactionDirection::Out,
                    amount: $amount,
                    occurredAt: $date,
                    reference: null,
                    description: 'دفعة مستحقات معالج',
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                );

                $payment = new PractitionerSettlementPayment;
                $payment->forceFill([
                    'practitioner_settlement_id' => $lockedSettlement->getKey(),
                    'cashbox_transaction_id' => $transaction->getKey(),
                    'amount' => $amount,
                    'paid_at' => $date,
                    'idempotency_key' => $idempotencyKey,
                    'paid_by' => $actor->getKey(),
                ]);
                $payment->save();
                $this->cashboxService->finalizeReference($transaction, $payment);

                $before = $lockedSettlement->getAttributes();
                $newPaidAmount = (int) $lockedSettlement->getAttribute('paid_amount') + $amount;
                $newRemainingAmount = (int) $lockedSettlement->getAttribute('total_due') - $newPaidAmount;
                $lockedSettlement->forceFill([
                    'paid_amount' => $newPaidAmount,
                    'remaining_amount' => $newRemainingAmount,
                    'status' => $newRemainingAmount === 0
                        ? SettlementStatus::Paid->value
                        : SettlementStatus::PartiallyPaid->value,
                ]);
                $lockedSettlement->save();

                $this->auditService->record(
                    'practitioner_settlement.payment_created',
                    $payment,
                    $actor,
                    before: $before,
                    after: [
                        ...$lockedSettlement->getAttributes(),
                        'settlement_payment_id' => $payment->getKey(),
                    ],
                );
                $practitioner = Practitioner::query()
                    ->withTrashed()
                    ->findOrFail((int) $lockedSettlement->getAttribute('practitioner_id'));
                $this->notifications->financial(
                    'settlement-payment:'.$payment->getKey().':recorded',
                    ClinicNotificationType::PractitionerSettlementRecorded,
                    '💵 تم دفع مستحقات معالج',
                    "المعالج: {$practitioner->name}\n"
                        .'المبلغ: '.number_format($amount)." ل.س\n"
                        .'المتبقي: '.number_format($newRemainingAmount).' ل.س',
                    route('finance.settlements'),
                    ['settlement_payment_id' => $payment->getKey(), 'amount' => $amount],
                    $practitioner,
                    '💵 تم تسجيل دفعة من مستحقاتك',
                    'المبلغ: '.number_format($amount)." ل.س\n"
                        .'المتبقي: '.number_format($newRemainingAmount).' ل.س',
                );

                return $payment->refresh();
            }, 3);
        } catch (QueryException $exception) {
            /** @var PractitionerSettlementPayment|null $existing */
            $existing = PractitionerSettlementPayment::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingPayment($existing, $settlement, $amount);

            return $existing;
        }
    }

    public function balance(Practitioner|int $practitioner): int
    {
        $practitionerId = $practitioner instanceof Practitioner
            ? (int) $practitioner->getKey()
            : $practitioner;
        $remaining = PractitionerSettlement::query()
            ->join('financial_weeks', 'financial_weeks.id', '=', 'practitioner_settlements.financial_week_id')
            ->where('practitioner_settlements.practitioner_id', $practitionerId)
            ->orderByDesc('financial_weeks.starts_on')
            ->orderByDesc('practitioner_settlements.id')
            ->value('practitioner_settlements.remaining_amount');

        return (int) ($remaining ?? 0);
    }

    public function accruedBalance(Practitioner|int $practitioner, ?CarbonInterface $at = null): int
    {
        $id = $practitioner instanceof Practitioner ? (int) $practitioner->getKey() : $practitioner;
        $week = $this->financialWeekService->forDate($at ?? now());
        $earnings = (int) PractitionerEarning::query()->where('practitioner_id', $id)->where('financial_week_id', $week->getKey())->sum('amount');
        $adjustments = (int) FinancialAdjustment::query()->where('practitioner_id', $id)->where('financial_week_id', $week->getKey())->sum('amount');
        $settlement = PractitionerSettlement::query()->where('practitioner_id', $id)->where('financial_week_id', $week->getKey())->first();
        $opening = (int) ($settlement?->opening_balance ?? PractitionerSettlement::query()
            ->join('financial_weeks', 'financial_weeks.id', '=', 'practitioner_settlements.financial_week_id')
            ->where('practitioner_settlements.practitioner_id', $id)
            ->where('financial_weeks.starts_on', '<', $week->getAttribute('starts_on'))
            ->orderByDesc('financial_weeks.starts_on')->orderByDesc('practitioner_settlements.id')
            ->value('remaining_amount') ?? 0);
        $paid = (int) ($settlement?->paid_amount ?? 0);

        return max(0, $opening + $earnings + $adjustments - $paid);
    }

    public function settleCurrentBalance(
        Practitioner|int $practitioner,
        int $amount,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $paidAt = null,
    ): PractitionerSettlementPayment {
        $practitionerId = $practitioner instanceof Practitioner ? (int) $practitioner->getKey() : $practitioner;
        $date = $paidAt ?? now();

        return DB::transaction(function () use ($practitionerId, $amount, $actor, $idempotencyKey, $date): PractitionerSettlementPayment {
            $week = $this->financialWeekService->forDate($date, true);
            $earnings = (int) PractitionerEarning::query()
                ->where('practitioner_id', $practitionerId)
                ->where('financial_week_id', $week->getKey())
                ->sum('amount');
            $adjustments = (int) FinancialAdjustment::query()
                ->where('practitioner_id', $practitionerId)
                ->where('financial_week_id', $week->getKey())
                ->sum('amount');
            $prior = PractitionerSettlement::query()
                ->join('financial_weeks', 'financial_weeks.id', '=', 'practitioner_settlements.financial_week_id')
                ->where('practitioner_settlements.practitioner_id', $practitionerId)
                ->where('financial_weeks.starts_on', '<', $week->getAttribute('starts_on'))
                ->orderByDesc('financial_weeks.starts_on')->orderByDesc('practitioner_settlements.id')
                ->select('practitioner_settlements.*')->first();
            $settlement = PractitionerSettlement::query()
                ->where('practitioner_id', $practitionerId)->where('financial_week_id', $week->getKey())
                ->lockForUpdate()->first();
            $opening = $settlement?->opening_balance ?? (int) ($prior?->remaining_amount ?? 0);
            $paid = (int) ($settlement?->paid_amount ?? 0);
            $settlement ??= new PractitionerSettlement;
            $settlement->forceFill([
                'practitioner_id' => $practitionerId, 'financial_week_id' => $week->getKey(),
                'opening_balance' => $opening, 'earnings_amount' => $earnings,
                'adjustments_amount' => $adjustments, 'total_due' => $opening + $earnings + $adjustments,
                'paid_amount' => $paid, 'remaining_amount' => $opening + $earnings + $adjustments - $paid,
                'status' => $paid > 0 ? SettlementStatus::PartiallyPaid->value : SettlementStatus::Pending->value,
            ])->save();

            return $this->pay($settlement, $amount, $actor, $idempotencyKey, $date);
        }, 3);
    }

    private function assertMatchingPayment(
        PractitionerSettlementPayment $payment,
        PractitionerSettlement $settlement,
        int $amount,
    ): void {
        if ((int) $payment->getAttribute('practitioner_settlement_id') !== (int) $settlement->getKey()
            || (int) $payment->getAttribute('amount') !== $amount) {
            throw FinancialException::duplicateOperation();
        }
    }
}
