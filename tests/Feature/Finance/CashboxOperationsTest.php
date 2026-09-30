<?php

use App\Exceptions\FinancialException;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\CashboxTransfer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAuditLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\CashboxTransferService;
use App\Services\ExpenseService;
use App\Services\FinancialWeekService;
use App\Services\WeeklyClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function cashboxActorWithPermissions(string ...$permissionNames): User
{
    $role = Role::factory()->create();

    foreach ($permissionNames as $permissionName) {
        $role->permissions()->attach(Permission::factory()->create([
            'name' => $permissionName,
            'label' => $permissionName,
        ]));
    }

    return User::factory()->for($role)->create();
}

it('derives cashbox balance from an append-only incoming and outgoing ledger', function () {
    $main = Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $actor = cashboxActorWithPermissions('cashboxes.manage');
    $service = app(CashboxService::class);
    $date = CarbonImmutable::parse('2026-09-08 12:00:00');

    $deposit = $service->deposit($main, 100_000, 'Opening float', $actor, (string) Str::uuid(), $date);
    $withdrawal = $service->withdraw($main, 30_000, 'Cash removal', $actor, (string) Str::uuid(), $date);

    expect($deposit->amount)->toBe(100_000)
        ->and($withdrawal->amount)->toBe(30_000)
        ->and($service->balance($main))->toBe(70_000);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 2);
});

it('makes manual cashbox operations idempotent and rejects changed retries', function () {
    $main = Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $actor = cashboxActorWithPermissions('cashboxes.manage');
    $service = app(CashboxService::class);
    $key = (string) Str::uuid();
    $date = CarbonImmutable::parse('2026-09-08 12:00:00');

    $first = $service->deposit($main, 100_000, 'Opening float', $actor, $key, $date);
    $second = $service->deposit($main, 100_000, 'Opening float', $actor, $key, $date);
    $changedRetry = fn () => $service->deposit($main, 90_000, 'Opening float', $actor, $key, $date);

    expect($second->is($first))->toBeTrue()
        ->and($changedRetry)->toThrow(FinancialException::class, 'لعملية مختلفة')
        ->and($service->balance($main))->toBe(100_000);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 1);
});

it('rejects a withdrawal that exceeds cash and leaves the ledger unchanged', function () {
    $main = Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $actor = cashboxActorWithPermissions('cashboxes.manage');

    $action = fn () => app(CashboxService::class)->withdraw(
        $main,
        1,
        'Unavailable cash',
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );

    expect($action)->toThrow(FinancialException::class, 'رصيد نقدي كافٍ');
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 0);
});

it('transfers cash atomically with balanced ledgers and idempotent retries', function () {
    $main = Cashbox::factory()->main()->create();
    $side = Cashbox::factory()->side()->create();
    $actor = cashboxActorWithPermissions('cashboxes.manage');
    $cashboxService = app(CashboxService::class);
    $date = CarbonImmutable::parse('2026-09-08 12:00:00');
    $cashboxService->deposit($main, 100_000, 'Opening float', $actor, (string) Str::uuid(), $date);
    $key = (string) Str::uuid();
    $service = app(CashboxTransferService::class);

    $first = $service->transfer($main, $side, 40_000, 'Reserve cash', $actor, $key, $date);
    $second = $service->transfer($main, $side, 40_000, 'Reserve cash', $actor, $key, $date);

    expect($second->is($first))->toBeTrue()
        ->and($cashboxService->balance($main))->toBe(60_000)
        ->and($cashboxService->balance($side))->toBe(40_000)
        ->and($first->outTransaction->reference_id)->toBe($first->id)
        ->and($first->inTransaction->reference_id)->toBe($first->id);
    $this->assertDatabaseCount((new CashboxTransfer)->getTable(), 1);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 3);
});

it('rejects a transfer above source balance without creating either ledger entry', function () {
    $main = Cashbox::factory()->main()->create();
    $side = Cashbox::factory()->side()->create();
    $actor = cashboxActorWithPermissions('cashboxes.manage');

    $action = fn () => app(CashboxTransferService::class)->transfer(
        $main,
        $side,
        1,
        null,
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );

    expect($action)->toThrow(FinancialException::class, 'رصيد نقدي كافٍ');
    $this->assertDatabaseCount((new CashboxTransfer)->getTable(), 0);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 0);
});

it('counts only expenses marked as affecting weekly net while both reduce physical cash', function () {
    $main = Cashbox::factory()->main()->create();
    $side = Cashbox::factory()->side()->create();
    $actor = cashboxActorWithPermissions('cashboxes.manage', 'expenses.manage');
    $date = CarbonImmutable::parse('2026-09-08 12:00:00');
    app(CashboxService::class)->deposit($side, 100_000, 'Expense float', $actor, (string) Str::uuid(), $date);
    $category = ExpenseCategory::factory()->create();
    $service = app(ExpenseService::class);

    $included = $service->record($category, $side, 10_000, 'Rent', true, $actor, (string) Str::uuid(), $date);
    $excluded = $service->record($category, $side, 20_000, 'Owner draw', false, $actor, (string) Str::uuid(), $date);
    $summary = app(WeeklyClosingService::class)->preview(app(FinancialWeekService::class)->forDate($date));

    expect($included->affects_weekly_net)->toBeTrue()
        ->and($excluded->affects_weekly_net)->toBeFalse()
        ->and($summary['expenses'])->toBe(10_000)
        ->and($summary['calculated_net'])->toBe(-10_000)
        ->and(app(CashboxService::class)->balance($side))->toBe(70_000)
        ->and(app(CashboxService::class)->balance($main))->toBe(0);
    $this->assertDatabaseCount((new Expense)->getTable(), 2);
});

it('requires explicit permissions for manual cash and expense operations', function () {
    $main = Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $category = ExpenseCategory::factory()->create();
    $actor = cashboxActorWithPermissions();

    $deposit = fn () => app(CashboxService::class)->deposit($main, 10_000, null, $actor, (string) Str::uuid());
    $expense = fn () => app(ExpenseService::class)->record($category, $main, 10_000, null, true, $actor, (string) Str::uuid());

    expect($deposit)->toThrow(FinancialException::class, 'لا تملك الصلاحية اللازمة')
        ->and($expense)->toThrow(FinancialException::class, 'لا تملك الصلاحية اللازمة');
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 0);
    $this->assertDatabaseCount((new Expense)->getTable(), 0);
});

it('rejects mutation and deletion of finalized financial records', function () {
    $transaction = CashboxTransaction::factory()->create();
    $payment = Payment::factory()->create();
    $auditLog = FinancialAuditLog::factory()->create();
    $transactionAmount = $transaction->amount;
    $paymentAmount = $payment->amount;

    expect(fn () => $transaction->update(['amount' => $transactionAmount + 1]))
        ->toThrow(FinancialException::class, 'حركة صندوق نهائية')
        ->and(fn () => $transaction->fresh()->delete())
        ->toThrow(FinancialException::class, 'حركة صندوق نهائية')
        ->and(fn () => $payment->update(['amount' => $paymentAmount + 1]))
        ->toThrow(FinancialException::class, 'حركة صندوق نهائية')
        ->and(fn () => $auditLog->delete())
        ->toThrow(FinancialException::class, 'حركة صندوق نهائية');

    expect($transaction->fresh()->amount)->toBe($transactionAmount)
        ->and($payment->fresh()->amount)->toBe($paymentAmount)
        ->and($auditLog->fresh())->not->toBeNull();
});
