<?php

use App\Enums\SettlementStatus;
use App\Exceptions\FinancialException;
use App\Models\Appointment;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\CashboxTransfer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialWeek;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\PractitionerSettlement;
use App\Models\PractitionerSettlementPayment;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use App\Models\User;
use App\Models\WeeklyClosing;
use App\Services\CashboxService;
use App\Services\ExpenseService;
use App\Services\FinancialWeekService;
use App\Services\PractitionerSettlementService;
use App\Services\WeeklyClosingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function closingActorWithPermissions(string ...$permissionNames): User
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

/**
 * @return array{week: FinancialWeek, practitioner: Practitioner, main: Cashbox, side: Cashbox}
 */
function createClosingWeekWithEarning(User $actor): array
{
    $week = FinancialWeek::factory()->create([
        'starts_on' => '2026-09-05',
        'ends_on' => '2026-09-10',
        'status' => 'open',
    ]);
    $main = Cashbox::factory()->main()->create();
    $side = Cashbox::factory()->side()->create();
    $practitioner = Practitioner::factory()->create();
    $treatmentType = TreatmentType::factory()->create();
    $appointment = Appointment::factory()->for($practitioner)->for($treatmentType)->create([
        'starts_at' => '2026-09-08 10:00:00',
        'ends_at' => '2026-09-08 11:00:00',
        'duration_minutes' => 60,
    ]);
    $session = TreatmentSession::factory()->for($appointment)->create();
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->create([
        'original_price' => 200_000,
        'discount_amount' => 0,
        'final_amount' => 200_000,
        'practitioner_percentage' => 60,
        'practitioner_share' => 120_000,
        'clinic_share' => 80_000,
    ]);
    $transaction = CashboxTransaction::factory()->for($main)->for($week)->create([
        'amount' => 200_000,
        'occurred_at' => '2026-09-08 12:00:00',
        'created_by' => $actor->id,
    ]);
    $payment = Payment::factory()->for($participant, 'treatmentSessionParticipant')->for($week)->create([
        'cashbox_transaction_id' => $transaction->id,
        'amount' => 200_000,
        'paid_at' => '2026-09-08 12:00:00',
        'received_by' => $actor->id,
    ]);
    PractitionerEarning::factory()->for($payment)->for($participant, 'treatmentSessionParticipant')->for($practitioner)->for($week)->create([
        'gross_amount' => 200_000,
        'practitioner_percentage' => 60,
        'amount' => 120_000,
        'earned_at' => '2026-09-08 12:00:00',
    ]);
    Expense::factory()->for($side)->for($week)->create([
        'amount' => 20_000,
        'occurred_at' => '2026-09-09 12:00:00',
        'affects_weekly_net' => true,
        'created_by' => $actor->id,
    ]);

    return compact('week', 'practitioner', 'main', 'side');
}

it('closes a week with calculated net reserved liabilities surplus transfer and practitioner settlement', function () {
    $actor = closingActorWithPermissions('cashboxes.manage', 'settlements.manage');
    ['week' => $week, 'practitioner' => $practitioner, 'main' => $main, 'side' => $side] = createClosingWeekWithEarning($actor);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 09:00:00'));
    $service = app(WeeklyClosingService::class);
    $preview = $service->preview($week);

    $closing = $service->close($week, $actor, (string) Str::uuid());
    $settlement = PractitionerSettlement::query()
        ->where('practitioner_id', $practitioner->id)
        ->where('financial_week_id', $week->id)
        ->sole();

    expect($preview['paid_sessions_count'])->toBe(1)
        ->and($preview['collections'])->toBe(200_000)
        ->and($preview['practitioner_shares'])->toBe(120_000)
        ->and($preview['expenses'])->toBe(20_000)
        ->and($preview['calculated_net'])->toBe(60_000)
        ->and($preview['available_cash'])->toBe(200_000)
        ->and($preview['reserved_practitioner_liabilities'])->toBe(120_000)
        ->and($preview['transferable_surplus'])->toBe(60_000)
        ->and($closing->transferred_surplus)->toBe(60_000)
        ->and($week->refresh()->status->value)->toBe('closed')
        ->and($settlement->opening_balance)->toBe(0)
        ->and($settlement->earnings_amount)->toBe(120_000)
        ->and($settlement->total_due)->toBe(120_000)
        ->and($settlement->remaining_amount)->toBe(120_000)
        ->and($settlement->status)->toBe(SettlementStatus::Pending)
        ->and(app(CashboxService::class)->balance($main))->toBe(140_000)
        ->and(app(CashboxService::class)->balance($side))->toBe(40_000);
    $this->assertDatabaseCount((new CashboxTransfer)->getTable(), 1);
    $this->assertDatabaseCount((new WeeklyClosing)->getTable(), 1);
});

it('returns the existing closing when the same financial week is closed again', function () {
    $actor = closingActorWithPermissions('cashboxes.manage');
    ['week' => $week] = createClosingWeekWithEarning($actor);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 09:00:00'));
    $service = app(WeeklyClosingService::class);

    $first = $service->close($week, $actor, (string) Str::uuid());
    $second = $service->close($week, $actor, (string) Str::uuid());

    expect($second->is($first))->toBeTrue();
    $this->assertDatabaseCount((new WeeklyClosing)->getTable(), 1);
    $this->assertDatabaseCount((new CashboxTransfer)->getTable(), 1);
});

it('covers only the physically available portion of a weekly deficit from the side cashbox', function () {
    $actor = closingActorWithPermissions('cashboxes.manage', 'expenses.manage');
    $main = Cashbox::factory()->main()->create();
    $side = Cashbox::factory()->side()->create();
    $date = CarbonImmutable::parse('2026-09-08 12:00:00');
    $cashboxService = app(CashboxService::class);
    $cashboxService->deposit($side, 50_000, 'Reserve', $actor, (string) Str::uuid(), $date);
    app(ExpenseService::class)->record(
        ExpenseCategory::factory()->create(),
        $side,
        30_000,
        'Weekly expense',
        true,
        $actor,
        (string) Str::uuid(),
        $date,
    );
    $week = app(FinancialWeekService::class)->forDate($date);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 09:00:00'));

    $closing = app(WeeklyClosingService::class)->close($week, $actor, (string) Str::uuid());

    expect($closing->calculated_net)->toBe(-30_000)
        ->and($closing->deficit_covered)->toBe(20_000)
        ->and(app(CashboxService::class)->balance($main))->toBe(20_000)
        ->and(app(CashboxService::class)->balance($side))->toBe(0);
});

it('supports idempotent partial settlement payment and carries the remainder into the next week', function () {
    $actor = closingActorWithPermissions('cashboxes.manage', 'settlements.manage');
    ['week' => $firstWeek, 'practitioner' => $practitioner, 'main' => $main] = createClosingWeekWithEarning($actor);
    $closingService = app(WeeklyClosingService::class);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 09:00:00'));
    $closingService->close($firstWeek, $actor, (string) Str::uuid());
    $firstSettlement = PractitionerSettlement::query()
        ->where('practitioner_id', $practitioner->id)
        ->where('financial_week_id', $firstWeek->id)
        ->sole();
    $paymentKey = (string) Str::uuid();
    $paidAt = CarbonImmutable::parse('2026-09-12 12:00:00');
    $settlementService = app(PractitionerSettlementService::class);

    $firstPayment = $settlementService->pay($firstSettlement, 50_000, $actor, $paymentKey, $paidAt);
    $retry = $settlementService->pay($firstSettlement, 50_000, $actor, $paymentKey, $paidAt);
    $secondWeek = app(FinancialWeekService::class)->forDate($paidAt);
    $secondWeekPreview = $closingService->preview($secondWeek);

    expect($retry->is($firstPayment))->toBeTrue()
        ->and($firstSettlement->refresh()->paid_amount)->toBe(50_000)
        ->and($firstSettlement->remaining_amount)->toBe(70_000)
        ->and($firstSettlement->status)->toBe(SettlementStatus::PartiallyPaid)
        ->and($secondWeekPreview['reserved_practitioner_liabilities'])->toBe(70_000)
        ->and($secondWeekPreview['practitioner_breakdown'][0]['opening_balance'])->toBe(70_000)
        ->and($secondWeekPreview['practitioner_breakdown'][0]['total_due'])->toBe(70_000)
        ->and(app(CashboxService::class)->balance($main))->toBe(90_000);
    $this->assertDatabaseCount((new PractitionerSettlementPayment)->getTable(), 1);

    $this->travelTo(CarbonImmutable::parse('2026-09-18 09:00:00'));
    $closingService->close($secondWeek, $actor, (string) Str::uuid());
    $carriedSettlement = PractitionerSettlement::query()
        ->where('practitioner_id', $practitioner->id)
        ->where('financial_week_id', $secondWeek->id)
        ->sole();

    expect($carriedSettlement->opening_balance)->toBe(70_000)
        ->and($carriedSettlement->earnings_amount)->toBe(0)
        ->and($carriedSettlement->remaining_amount)->toBe(70_000);
});

it('requires settlement permission and leaves settlement cash untouched', function () {
    $main = Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $week = FinancialWeek::factory()->create([
        'starts_on' => '2026-09-05',
        'ends_on' => '2026-09-10',
    ]);
    $settlement = PractitionerSettlement::factory()->for($week)->create([
        'opening_balance' => 0,
        'earnings_amount' => 40_000,
        'total_due' => 40_000,
        'paid_amount' => 0,
        'remaining_amount' => 40_000,
    ]);
    $actor = closingActorWithPermissions();

    $action = fn () => app(PractitionerSettlementService::class)->pay(
        $settlement,
        10_000,
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );

    expect($action)->toThrow(FinancialException::class, 'لا تملك الصلاحية اللازمة')
        ->and(app(CashboxService::class)->balance($main))->toBe(0);
    $this->assertDatabaseCount((new PractitionerSettlementPayment)->getTable(), 0);
});

it('supports an admin daily settlement against only the accrued balance', function () {
    $actor = closingActorWithPermissions('cashboxes.manage', 'settlements.manage');
    ['week' => $week, 'practitioner' => $practitioner, 'main' => $main] = createClosingWeekWithEarning($actor);
    $this->travelTo(CarbonImmutable::parse('2026-09-09 15:00:00'));

    $payment = app(PractitionerSettlementService::class)->settleCurrentBalance(
        $practitioner, 50_000, $actor, (string) Str::uuid(), now(),
    );

    expect($payment->amount)->toBe(50_000)
        ->and(PractitionerSettlement::query()->whereKey($payment->getAttribute('practitioner_settlement_id'))->value('remaining_amount'))->toBe(70_000)
        ->and(app(CashboxService::class)->balance($main))->toBe(150_000);
});

it('settles an older liability through todays open period without changing historical records', function () {
    $actor = closingActorWithPermissions('cashboxes.manage', 'settlements.manage');
    ['week' => $historicalWeek, 'practitioner' => $practitioner, 'main' => $main] = createClosingWeekWithEarning($actor);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 09:00:00'));
    app(WeeklyClosingService::class)->close($historicalWeek, $actor, (string) Str::uuid());

    $historicalPayment = Payment::query()->sole();
    $historicalPaidAt = $historicalPayment->paid_at->toDateTimeString();
    $historicalSessionStartedAt = TreatmentSession::query()->sole()->started_at->toDateTimeString();
    $historicalSettlement = PractitionerSettlement::query()
        ->where('financial_week_id', $historicalWeek->id)
        ->sole();
    $this->travelTo(CarbonImmutable::parse('2026-09-15 13:30:00'));

    $service = app(PractitionerSettlementService::class);
    $partial = $service->settleCurrentBalance($practitioner, 50_000, $actor, (string) Str::uuid());
    $currentWeek = app(FinancialWeekService::class)->forDate(now(), true);
    $currentSettlement = PractitionerSettlement::query()
        ->where('practitioner_id', $practitioner->id)
        ->where('financial_week_id', $currentWeek->id)
        ->sole();

    expect($partial->paid_at->toDateTimeString())->toBe('2026-09-15 13:30:00')
        ->and($partial->cashboxTransaction->financial_week_id)->toBe($currentWeek->id)
        ->and($currentSettlement->opening_balance)->toBe(120_000)
        ->and($currentSettlement->remaining_amount)->toBe(70_000)
        ->and($historicalSettlement->refresh()->remaining_amount)->toBe(120_000)
        ->and($historicalPayment->refresh()->paid_at->toDateTimeString())->toBe($historicalPaidAt)
        ->and(TreatmentSession::query()->sole()->started_at->toDateTimeString())->toBe($historicalSessionStartedAt)
        ->and(app(CashboxService::class)->balance($main))->toBe(90_000);

    $service->settleCurrentBalance($practitioner, 70_000, $actor, (string) Str::uuid());

    expect($currentSettlement->refresh()->remaining_amount)->toBe(0)
        ->and($currentSettlement->status)->toBe(SettlementStatus::Paid)
        ->and(app(CashboxService::class)->balance($main))->toBe(20_000);
    $this->assertDatabaseCount((new PractitionerSettlementPayment)->getTable(), 2);
    $this->assertDatabaseHas('financial_audit_logs', [
        'action' => 'practitioner_settlement.payment_created',
        'user_id' => $actor->id,
    ]);
});

it('allows an authorized manager to close a financial period before Thursday', function () {
    $actor = closingActorWithPermissions('cashboxes.manage');
    Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $week = FinancialWeek::factory()->create([
        'starts_on' => '2026-09-05',
        'ends_on' => '2026-09-10',
        'status' => 'open',
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-09-10 23:59:59'));

    $closing = app(WeeklyClosingService::class)->close(
        $week,
        $actor,
        (string) Str::uuid(),
    );

    expect($closing)->toBeInstanceOf(WeeklyClosing::class);
    $this->assertDatabaseCount((new WeeklyClosing)->getTable(), 1);
    expect($week->refresh()->status->value)->toBe('closed');
});

it('requires older open financial weeks to be closed first', function () {
    $actor = closingActorWithPermissions('cashboxes.manage');
    FinancialWeek::factory()->create([
        'starts_on' => '2026-09-05',
        'ends_on' => '2026-09-10',
        'status' => 'open',
    ]);
    $laterWeek = FinancialWeek::factory()->create([
        'starts_on' => '2026-09-12',
        'ends_on' => '2026-09-17',
        'status' => 'open',
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-09-18 09:00:00'));

    $action = fn () => app(WeeklyClosingService::class)->close(
        $laterWeek,
        $actor,
        (string) Str::uuid(),
    );

    expect($action)->toThrow(FinancialException::class, 'أقدم أسبوع مالي مفتوح');
    $this->assertDatabaseCount((new WeeklyClosing)->getTable(), 0);
    expect($laterWeek->refresh()->status->value)->toBe('open');
});
