<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class ExpenseService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly CashboxService $cashboxService,
        private readonly FinancialAuditService $auditService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function record(
        ExpenseCategory $category,
        Cashbox $cashbox,
        int $amount,
        ?string $description,
        bool $affectsWeeklyNet,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $occurredAt = null,
    ): Expense {
        if (! $actor->hasPermission('expenses.manage')) {
            throw FinancialException::permissionDenied('expenses.manage');
        }

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);
        $this->cashboxService->assertPositiveAmount($amount);

        try {
            return DB::transaction(function () use (
                $category,
                $cashbox,
                $amount,
                $description,
                $affectsWeeklyNet,
                $actor,
                $idempotencyKey,
                $occurredAt,
            ): Expense {
                /** @var Expense|null $idempotentExpense */
                $idempotentExpense = Expense::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($idempotentExpense !== null) {
                    $this->assertMatchingExpense(
                        $idempotentExpense,
                        $category,
                        $cashbox,
                        $amount,
                        $description,
                        $affectsWeeklyNet,
                    );

                    return $idempotentExpense;
                }

                /** @var ExpenseCategory $lockedCategory */
                $lockedCategory = ExpenseCategory::query()
                    ->whereKey($category->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! (bool) $lockedCategory->getAttribute('is_active')) {
                    throw FinancialException::permissionDenied('active expense category');
                }

                $date = $occurredAt ?? now();
                $week = $this->financialWeekService->forDate($date, true);
                $transaction = $this->cashboxService->record(
                    cashbox: $cashbox,
                    financialWeek: $week,
                    type: TransactionType::Expense,
                    direction: TransactionDirection::Out,
                    amount: $amount,
                    occurredAt: $date,
                    reference: null,
                    description: $description,
                    actor: $actor,
                    idempotencyKey: $idempotencyKey,
                );

                $expense = new Expense;
                $expense->forceFill([
                    'expense_category_id' => $lockedCategory->getKey(),
                    'cashbox_id' => $transaction->getAttribute('cashbox_id'),
                    'financial_week_id' => $week->getKey(),
                    'cashbox_transaction_id' => $transaction->getKey(),
                    'amount' => $amount,
                    'occurred_at' => $date,
                    'description' => $description === null ? null : trim($description),
                    'affects_weekly_net' => $affectsWeeklyNet,
                    'idempotency_key' => $idempotencyKey,
                    'created_by' => $actor->getKey(),
                ]);
                $expense->save();
                $this->cashboxService->finalizeReference($transaction, $expense);
                $this->auditService->record(
                    'expense.created',
                    $expense,
                    $actor,
                    after: $expense->getAttributes(),
                );
                $this->notifications->administrative(
                    'expense:'.$expense->getKey().':created',
                    ClinicNotificationType::ExpenseCreated,
                    '🧾 تم تسجيل مصروف',
                    "الفئة: {$lockedCategory->name}\n"
                        .'المبلغ: '.number_format($amount)." ل.س\n"
                        .'التاريخ: '.$date->format('Y/m/d H:i'),
                    route('finance.expenses'),
                    ['expense_id' => $expense->getKey(), 'amount' => $amount],
                );

                return $expense->refresh();
            }, 3);
        } catch (QueryException $exception) {
            /** @var Expense|null $existing */
            $existing = Expense::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            $this->assertMatchingExpense($existing, $category, $cashbox, $amount, $description, $affectsWeeklyNet);

            return $existing;
        }
    }

    private function assertMatchingExpense(
        Expense $expense,
        ExpenseCategory $category,
        Cashbox $cashbox,
        int $amount,
        ?string $description,
        bool $affectsWeeklyNet,
    ): void {
        $matches = (int) $expense->getAttribute('expense_category_id') === (int) $category->getKey()
            && (int) $expense->getAttribute('cashbox_id') === (int) $cashbox->getKey()
            && (int) $expense->getAttribute('amount') === $amount
            && (string) ($expense->getAttribute('description') ?? '') === trim((string) ($description ?? ''))
            && (bool) $expense->getAttribute('affects_weekly_net') === $affectsWeeklyNet;

        if (! $matches) {
            throw FinancialException::duplicateOperation();
        }
    }
}
