<?php

namespace App\Services;

use App\Enums\CashboxType;
use App\Enums\ClinicNotificationType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\FinancialWeek;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CashboxService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly FinancialAuditService $auditService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function main(bool $lockForUpdate = false): Cashbox
    {
        return $this->byType(CashboxType::Main, $lockForUpdate);
    }

    public function side(bool $lockForUpdate = false): Cashbox
    {
        return $this->byType(CashboxType::Side, $lockForUpdate);
    }

    public function balance(Cashbox|int $cashbox): int
    {
        $cashboxId = $cashbox instanceof Cashbox ? (int) $cashbox->getKey() : $cashbox;
        $incoming = (int) CashboxTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('direction', TransactionDirection::In->value)
            ->sum('amount');
        $outgoing = (int) CashboxTransaction::query()
            ->where('cashbox_id', $cashboxId)
            ->where('direction', TransactionDirection::Out->value)
            ->sum('amount');

        return $incoming - $outgoing;
    }

    public function deposit(
        Cashbox $cashbox,
        int $amount,
        ?string $description,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $occurredAt = null,
    ): CashboxTransaction {
        $this->assertPermission($actor, 'cashboxes.manage');

        return DB::transaction(function () use (
            $cashbox,
            $amount,
            $description,
            $actor,
            $idempotencyKey,
            $occurredAt,
        ): CashboxTransaction {
            $date = $occurredAt ?? now();
            $week = $this->financialWeekService->forDate($date, true);
            $transaction = $this->record(
                cashbox: $cashbox,
                financialWeek: $week,
                type: TransactionType::ManualDeposit,
                direction: TransactionDirection::In,
                amount: $amount,
                occurredAt: $date,
                reference: null,
                description: $description,
                actor: $actor,
                idempotencyKey: $idempotencyKey,
            );

            if ($transaction->wasRecentlyCreated) {
                $this->auditService->record(
                    'cashbox.deposit',
                    $transaction,
                    $actor,
                    after: $transaction->getAttributes(),
                );

                if ($this->enumValue($cashbox->getAttribute('type')) === CashboxType::Side->value) {
                    $this->notifications->administrative(
                        'cashbox-transaction:'.$transaction->getKey().':deposit',
                        ClinicNotificationType::SideCashboxDeposit,
                        '➕ إيداع في الصندوق الجانبي',
                        'المبلغ: '.number_format($amount)." ل.س\n".'البيان: '.trim((string) $description),
                        route('finance.cashboxes'),
                        ['cashbox_transaction_id' => $transaction->getKey(), 'amount' => $amount],
                    );
                }
            }

            return $transaction;
        }, 3);
    }

    public function withdraw(
        Cashbox $cashbox,
        int $amount,
        ?string $description,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $occurredAt = null,
    ): CashboxTransaction {
        $this->assertPermission($actor, 'cashboxes.manage');

        return DB::transaction(function () use (
            $cashbox,
            $amount,
            $description,
            $actor,
            $idempotencyKey,
            $occurredAt,
        ): CashboxTransaction {
            $date = $occurredAt ?? now();
            $week = $this->financialWeekService->forDate($date, true);
            $transaction = $this->record(
                cashbox: $cashbox,
                financialWeek: $week,
                type: TransactionType::ManualWithdrawal,
                direction: TransactionDirection::Out,
                amount: $amount,
                occurredAt: $date,
                reference: null,
                description: $description,
                actor: $actor,
                idempotencyKey: $idempotencyKey,
            );

            if ($transaction->wasRecentlyCreated) {
                $this->auditService->record(
                    'cashbox.withdrawal',
                    $transaction,
                    $actor,
                    after: $transaction->getAttributes(),
                );

                if ($this->enumValue($cashbox->getAttribute('type')) === CashboxType::Side->value) {
                    $this->notifications->administrative(
                        'cashbox-transaction:'.$transaction->getKey().':withdrawal',
                        ClinicNotificationType::SideCashboxWithdrawal,
                        '➖ سحب من الصندوق الجانبي',
                        'المبلغ: '.number_format($amount)." ل.س\n".'البيان: '.trim((string) $description),
                        route('finance.cashboxes'),
                        ['cashbox_transaction_id' => $transaction->getKey(), 'amount' => $amount],
                    );
                }
            }

            return $transaction;
        }, 3);
    }

    public function record(
        Cashbox|int $cashbox,
        FinancialWeek $financialWeek,
        TransactionType|string $type,
        TransactionDirection|string $direction,
        int $amount,
        CarbonInterface|DateTimeInterface|string $occurredAt,
        ?Model $reference,
        ?string $description,
        ?User $actor,
        string $idempotencyKey,
    ): CashboxTransaction {
        $this->assertIdempotencyKey($idempotencyKey);
        $this->assertPositiveAmount($amount);
        $typeValue = $type instanceof TransactionType ? $type : TransactionType::from($type);
        $directionValue = $direction instanceof TransactionDirection ? $direction : TransactionDirection::from($direction);
        $cashboxId = $cashbox instanceof Cashbox ? (int) $cashbox->getKey() : $cashbox;
        $date = $this->dateTime($occurredAt);

        try {
            return DB::transaction(function () use (
                $cashboxId,
                $financialWeek,
                $typeValue,
                $directionValue,
                $amount,
                $date,
                $reference,
                $description,
                $actor,
                $idempotencyKey,
            ): CashboxTransaction {
                /** @var CashboxTransaction|null $existing */
                $existing = CashboxTransaction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertMatchingTransaction(
                        $existing,
                        $cashboxId,
                        $typeValue,
                        $directionValue,
                        $amount,
                        $description,
                    );

                    return $existing;
                }

                /** @var Cashbox $lockedCashbox */
                $lockedCashbox = Cashbox::query()->whereKey($cashboxId)->lockForUpdate()->firstOrFail();
                /** @var FinancialWeek $lockedWeek */
                $lockedWeek = FinancialWeek::query()
                    ->whereKey($financialWeek->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! (bool) $lockedCashbox->getAttribute('is_active')) {
                    throw FinancialException::permissionDenied('active cashbox');
                }

                $this->financialWeekService->assertOpen($lockedWeek);

                if (! $this->financialWeekService->contains($lockedWeek, $date)) {
                    throw FinancialException::weekMismatch();
                }

                if ($directionValue === TransactionDirection::Out && $this->balance($lockedCashbox) < $amount) {
                    throw FinancialException::insufficientCash();
                }

                $transaction = new CashboxTransaction;
                $transaction->forceFill([
                    'cashbox_id' => $lockedCashbox->getKey(),
                    'financial_week_id' => $lockedWeek->getKey(),
                    'type' => $typeValue->value,
                    'direction' => $directionValue->value,
                    'amount' => $amount,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id' => $reference?->getKey(),
                    'description' => $description === null ? null : trim($description),
                    'occurred_at' => $date,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $actor?->getKey(),
                ]);
                $transaction->save();

                return $transaction;
            }, 3);
        } catch (QueryException $exception) {
            /** @var CashboxTransaction|null $existing */
            $existing = CashboxTransaction::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingTransaction(
                $existing,
                $cashboxId,
                $typeValue,
                $directionValue,
                $amount,
                $description,
            );

            return $existing;
        }
    }

    public function finalizeReference(CashboxTransaction $transaction, Model $reference): CashboxTransaction
    {
        return DB::transaction(function () use ($transaction, $reference): CashboxTransaction {
            /** @var CashboxTransaction $lockedTransaction */
            $lockedTransaction = CashboxTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $referenceType = $reference->getMorphClass();
            $referenceId = (int) $reference->getKey();

            if ($lockedTransaction->getAttribute('reference_type') !== null) {
                $matches = (string) $lockedTransaction->getAttribute('reference_type') === $referenceType
                    && (int) $lockedTransaction->getAttribute('reference_id') === $referenceId;

                if (! $matches) {
                    throw FinancialException::immutableLedgerEntry();
                }

                return $lockedTransaction;
            }

            $lockedTransaction->forceFill([
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
            $lockedTransaction->save();

            return $lockedTransaction;
        }, 3);
    }

    public function assertIdempotencyKey(string $idempotencyKey): void
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw FinancialException::invalidIdempotencyKey();
        }
    }

    public function assertPositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw FinancialException::invalidAmount();
        }
    }

    private function byType(CashboxType $type, bool $lockForUpdate): Cashbox
    {
        $query = Cashbox::query()->where('type', $type->value);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Cashbox $cashbox */
        $cashbox = $query->firstOrFail();

        return $cashbox;
    }

    private function assertMatchingTransaction(
        CashboxTransaction $transaction,
        int $cashboxId,
        TransactionType $type,
        TransactionDirection $direction,
        int $amount,
        ?string $description,
    ): void {
        $matches = (int) $transaction->getAttribute('cashbox_id') === $cashboxId
            && $this->enumValue($transaction->getAttribute('type')) === $type->value
            && $this->enumValue($transaction->getAttribute('direction')) === $direction->value
            && (int) $transaction->getAttribute('amount') === $amount
            && (string) ($transaction->getAttribute('description') ?? '') === trim((string) ($description ?? ''));

        if (! $matches) {
            throw FinancialException::duplicateOperation();
        }
    }

    private function assertPermission(User $actor, string $permission): void
    {
        if (! $actor->hasPermission($permission)) {
            throw FinancialException::permissionDenied($permission);
        }
    }

    private function dateTime(CarbonInterface|DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone((string) config('app.timezone'));
        }

        return CarbonImmutable::parse($value, (string) config('app.timezone'));
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
