<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\Cashbox;
use App\Models\CashboxTransfer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class CashboxTransferService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly CashboxService $cashboxService,
        private readonly FinancialAuditService $auditService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function transfer(
        Cashbox $fromCashbox,
        Cashbox $toCashbox,
        int $amount,
        ?string $reason,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $occurredAt = null,
    ): CashboxTransfer {
        if (! $actor->hasPermission('cashboxes.manage')) {
            throw FinancialException::permissionDenied('cashboxes.manage');
        }

        if ((int) $fromCashbox->getKey() === (int) $toCashbox->getKey()) {
            throw FinancialException::invalidAmount('Cashbox transfers require two different cashboxes.');
        }

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);
        $this->cashboxService->assertPositiveAmount($amount);

        try {
            return DB::transaction(function () use (
                $fromCashbox,
                $toCashbox,
                $amount,
                $reason,
                $actor,
                $idempotencyKey,
                $occurredAt,
            ): CashboxTransfer {
                /** @var CashboxTransfer|null $idempotentTransfer */
                $idempotentTransfer = CashboxTransfer::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($idempotentTransfer !== null) {
                    $this->assertMatchingTransfer($idempotentTransfer, $fromCashbox, $toCashbox, $amount, $reason);

                    return $idempotentTransfer;
                }

                $date = $occurredAt ?? now();
                $week = $this->financialWeekService->forDate($date, true);

                $cashboxes = Cashbox::query()
                    ->whereIn('id', [(int) $fromCashbox->getKey(), (int) $toCashbox->getKey()])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(static fn (Cashbox $cashbox): int => (int) $cashbox->getKey());

                /** @var Cashbox|null $lockedFrom */
                $lockedFrom = $cashboxes->get((int) $fromCashbox->getKey());
                /** @var Cashbox|null $lockedTo */
                $lockedTo = $cashboxes->get((int) $toCashbox->getKey());

                if ($lockedFrom === null || $lockedTo === null) {
                    throw FinancialException::invalidAmount('A selected cashbox no longer exists.');
                }

                if (! (bool) $lockedFrom->getAttribute('is_active') || ! (bool) $lockedTo->getAttribute('is_active')) {
                    throw FinancialException::permissionDenied('active cashboxes');
                }

                if ($this->cashboxService->balance($lockedFrom) < $amount) {
                    throw FinancialException::insufficientCash();
                }

                $outTransaction = $this->cashboxService->record(
                    cashbox: $lockedFrom,
                    financialWeek: $week,
                    type: TransactionType::TransferOut,
                    direction: TransactionDirection::Out,
                    amount: $amount,
                    occurredAt: $date,
                    reference: null,
                    description: $reason,
                    actor: $actor,
                    idempotencyKey: $this->derivedIdempotencyKey($idempotencyKey, 'out'),
                );
                $inTransaction = $this->cashboxService->record(
                    cashbox: $lockedTo,
                    financialWeek: $week,
                    type: TransactionType::TransferIn,
                    direction: TransactionDirection::In,
                    amount: $amount,
                    occurredAt: $date,
                    reference: null,
                    description: $reason,
                    actor: $actor,
                    idempotencyKey: $this->derivedIdempotencyKey($idempotencyKey, 'in'),
                );

                $transfer = new CashboxTransfer;
                $transfer->forceFill([
                    'from_cashbox_id' => $lockedFrom->getKey(),
                    'to_cashbox_id' => $lockedTo->getKey(),
                    'financial_week_id' => $week->getKey(),
                    'out_transaction_id' => $outTransaction->getKey(),
                    'in_transaction_id' => $inTransaction->getKey(),
                    'amount' => $amount,
                    'occurred_at' => $date,
                    'reason' => $reason === null ? null : trim($reason),
                    'status' => 'completed',
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $actor->getKey(),
                ]);
                $transfer->save();
                $this->cashboxService->finalizeReference($outTransaction, $transfer);
                $this->cashboxService->finalizeReference($inTransaction, $transfer);
                $this->auditService->record(
                    'cashbox_transfer.created',
                    $transfer,
                    $actor,
                    after: $transfer->getAttributes(),
                );
                $this->notifications->administrative(
                    'cashbox-transfer:'.$transfer->getKey().':created',
                    ClinicNotificationType::CashboxTransferCreated,
                    '🔁 تم تحويل مبلغ بين صندوقين',
                    "من: {$lockedFrom->name}\n"
                        ."إلى: {$lockedTo->name}\n"
                        .'المبلغ: '.number_format($amount).' ل.س',
                    route('finance.cashboxes'),
                    ['cashbox_transfer_id' => $transfer->getKey(), 'amount' => $amount],
                );

                return $transfer->refresh();
            }, 3);
        } catch (QueryException $exception) {
            /** @var CashboxTransfer|null $existing */
            $existing = CashboxTransfer::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingTransfer($existing, $fromCashbox, $toCashbox, $amount, $reason);

            return $existing;
        }
    }

    private function derivedIdempotencyKey(string $idempotencyKey, string $direction): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "clinic:cashbox-transfer:{$direction}:{$idempotencyKey}",
        )->toString();
    }

    private function assertMatchingTransfer(
        CashboxTransfer $transfer,
        Cashbox $fromCashbox,
        Cashbox $toCashbox,
        int $amount,
        ?string $reason,
    ): void {
        $matches = (int) $transfer->getAttribute('from_cashbox_id') === (int) $fromCashbox->getKey()
            && (int) $transfer->getAttribute('to_cashbox_id') === (int) $toCashbox->getKey()
            && (int) $transfer->getAttribute('amount') === $amount
            && (string) ($transfer->getAttribute('reason') ?? '') === trim((string) ($reason ?? ''));

        if (! $matches) {
            throw FinancialException::duplicateOperation();
        }
    }
}
