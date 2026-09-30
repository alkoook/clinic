<?php

use App\Enums\ParticipantPaymentStatus;
use App\Enums\PatientSource;
use App\Enums\PaymentStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Exceptions\FinancialException;
use App\Models\Appointment;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\FinancialAdjustment;
use App\Models\FinancialAgreement;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\Refund;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\PaymentService;
use App\Services\PractitionerShareWaiverService;
use App\Services\RefundService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function paymentActorWithPermissions(string ...$permissionNames): User
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
 * @return array{0: TreatmentSessionParticipant, 1: Practitioner, 2: TreatmentType, 3: Cashbox, 4: FinancialAgreement}
 */
function createPaymentScenario(int $percentage = 60): array
{
    $mainCashbox = Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $practitioner = Practitioner::factory()->create();
    $treatmentType = TreatmentType::factory()->create();
    $appointment = Appointment::factory()->for($practitioner)->for($treatmentType)->create([
        'starts_at' => '2026-09-08 10:00:00',
        'ends_at' => '2026-09-08 11:00:00',
        'duration_minutes' => 60,
    ]);
    $session = TreatmentSession::factory()->for($appointment)->create([
        'started_at' => '2026-09-08 10:00:00',
        'ended_at' => '2026-09-08 11:00:00',
    ]);
    $patient = Patient::factory()->for($practitioner, 'currentPractitioner')->create([
        'source' => PatientSource::Clinic,
    ]);
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->create([
        'patient_source' => PatientSource::Clinic,
        'original_price' => 100_000,
        'discount_amount' => 0,
        'final_amount' => 100_000,
        'practitioner_percentage' => null,
        'practitioner_share' => null,
        'clinic_share' => null,
        'payment_status' => ParticipantPaymentStatus::Unpaid,
    ]);
    $agreement = FinancialAgreement::factory()->for($practitioner)->create([
        'practitioner_percentage' => $percentage,
        'effective_from' => '2026-01-01',
        'effective_to' => null,
        'is_active' => true,
    ]);

    return [$participant, $practitioner, $treatmentType, $mainCashbox, $agreement];
}

it('records a full cash payment ledger and immutable financial snapshots from the effective agreement', function () {
    [$participant, $practitioner, $treatmentType, $mainCashbox, $agreement] = createPaymentScenario(60);
    $actor = paymentActorWithPermissions('payments.create', 'discounts.apply');

    $payment = app(PaymentService::class)->pay(
        $participant,
        100_000,
        10_000,
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );
    $agreement->update(['practitioner_percentage' => 25]);
    $participant->refresh();
    $earning = PractitionerEarning::query()->where('payment_id', $payment->id)->sole();
    $ledger = CashboxTransaction::query()->findOrFail($payment->cashbox_transaction_id);

    expect($payment->amount)->toBe(90_000)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($participant->original_price)->toBe(100_000)
        ->and($participant->discount_amount)->toBe(10_000)
        ->and($participant->final_amount)->toBe(90_000)
        ->and($participant->practitioner_percentage)->toBe('60.00')
        ->and($participant->practitioner_share)->toBe(54_000)
        ->and($participant->clinic_share)->toBe(36_000)
        ->and($participant->financial_agreement_id)->toBe($agreement->id)
        ->and($earning->practitioner_id)->toBe($practitioner->id)
        ->and($earning->amount)->toBe(54_000)
        ->and($ledger->cashbox_id)->toBe($mainCashbox->id)
        ->and($ledger->type)->toBe(TransactionType::SessionPayment)
        ->and($ledger->direction)->toBe(TransactionDirection::In)
        ->and($ledger->reference_id)->toBe($payment->id)
        ->and(app(CashboxService::class)->balance($mainCashbox))->toBe(90_000);
    expect($participant->treatmentSession->treatment_type_id)->toBe($treatmentType->id);
});

it('refunds a practitioner share waiver without changing the payment snapshot', function () {
    [$participant, , , $mainCashbox] = createPaymentScenario(50);
    $actor = paymentActorWithPermissions('payments.create', 'refunds.create');
    $payment = app(PaymentService::class)->pay(
        $participant, 100_000, 0, $actor, (string) Str::uuid(), CarbonImmutable::parse('2026-09-08 12:00:00'),
    );
    $refund = app(PractitionerShareWaiverService::class)->waive(
        $payment, 50_000, $actor, (string) Str::uuid(), null, CarbonImmutable::parse('2026-09-08 13:00:00'),
    );
    $earning = PractitionerEarning::query()->where('payment_id', $payment->id)->sole();

    expect($refund->amount)->toBe(50_000)
        ->and($payment->fresh()->amount)->toBe(100_000)
        ->and($earning->amount)->toBe(0)
        ->and($participant->fresh()->practitioner_share)->toBe(50_000)
        ->and(app(CashboxService::class)->balance($mainCashbox))->toBe(50_000);
});

it('applies the same practitioner percentage across treatments and patient sources', function () {
    [$clinicParticipant, $practitioner, $firstTreatment, , $agreement] = createPaymentScenario(47);
    $secondTreatment = TreatmentType::factory()->create();
    $appointment = Appointment::factory()->for($practitioner)->for($secondTreatment)->create([
        'starts_at' => '2026-09-09 10:00:00',
        'ends_at' => '2026-09-09 11:00:00',
    ]);
    $session = TreatmentSession::factory()->for($appointment)->create(['started_at' => '2026-09-09 10:00:00']);
    $referredPatient = Patient::factory()->for($practitioner, 'currentPractitioner')->create([
        'source' => PatientSource::Practitioner,
        'referring_practitioner_id' => $practitioner->id,
    ]);
    $referredParticipant = TreatmentSessionParticipant::factory()->for($session)->for($referredPatient)->create([
        'patient_source' => PatientSource::Practitioner,
        'original_price' => 120_000,
        'final_amount' => 120_000,
        'payment_status' => ParticipantPaymentStatus::Unpaid,
    ]);
    $actor = paymentActorWithPermissions('payments.create');
    $service = app(PaymentService::class);

    $service->pay($clinicParticipant, 100_000, 0, $actor, (string) Str::uuid(), CarbonImmutable::parse('2026-09-08 12:00:00'));
    $service->pay($referredParticipant, 120_000, 0, $actor, (string) Str::uuid(), CarbonImmutable::parse('2026-09-09 12:00:00'));

    expect($clinicParticipant->fresh()->practitioner_percentage)->toBe('47.00')
        ->and($referredParticipant->fresh()->practitioner_percentage)->toBe('47.00')
        ->and($clinicParticipant->fresh()->financial_agreement_id)->toBe($agreement->id)
        ->and($referredParticipant->fresh()->financial_agreement_id)->toBe($agreement->id)
        ->and($clinicParticipant->fresh()->treatmentSession->treatment_type_id)->toBe($firstTreatment->id)
        ->and($referredParticipant->fresh()->treatmentSession->treatment_type_id)->toBe($secondTreatment->id);
});

it('returns the same payment for an identical idempotent retry without duplicating side effects', function () {
    [$participant] = createPaymentScenario();
    $actor = paymentActorWithPermissions('payments.create');
    $key = (string) Str::uuid();
    $service = app(PaymentService::class);

    $first = $service->pay($participant, 100_000, 0, $actor, $key, CarbonImmutable::parse('2026-09-08 12:00:00'));
    $second = $service->pay($participant, 100_000, 0, $actor, $key, CarbonImmutable::parse('2026-09-08 12:00:00'));

    expect($second->is($first))->toBeTrue();
    $this->assertDatabaseCount((new Payment)->getTable(), 1);
    $this->assertDatabaseCount((new PractitionerEarning)->getTable(), 1);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 1);
});

it('rejects reuse of a payment idempotency key for a different operation', function () {
    [$participant] = createPaymentScenario();
    $actor = paymentActorWithPermissions('payments.create', 'discounts.apply');
    $key = (string) Str::uuid();
    $service = app(PaymentService::class);
    $service->pay($participant, 100_000, 0, $actor, $key, CarbonImmutable::parse('2026-09-08 12:00:00'));

    $action = fn () => $service->pay($participant, 100_000, 1_000, $actor, $key, CarbonImmutable::parse('2026-09-08 12:00:00'));

    expect($action)->toThrow(FinancialException::class, 'لعملية مختلفة');
    $this->assertDatabaseCount((new Payment)->getTable(), 1);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 1);
});

it('requires payment permission before creating any financial side effect', function () {
    [$participant] = createPaymentScenario();
    $actor = paymentActorWithPermissions();

    $action = fn () => app(PaymentService::class)->pay($participant, 100_000, 0, $actor, (string) Str::uuid());

    expect($action)->toThrow(FinancialException::class, 'لا تملك الصلاحية اللازمة');
    $this->assertDatabaseCount((new Payment)->getTable(), 0);
    $this->assertDatabaseCount((new PractitionerEarning)->getTable(), 0);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 0);
});

it('requires discount permission and rolls back the attempted discounted payment', function () {
    [$participant] = createPaymentScenario();
    $actor = paymentActorWithPermissions('payments.create');

    $action = fn () => app(PaymentService::class)->pay($participant, 100_000, 10_000, $actor, (string) Str::uuid());

    expect($action)->toThrow(FinancialException::class, 'لا تملك الصلاحية اللازمة لاعتماد هذا الخصم');
    $this->assertDatabaseCount((new Payment)->getTable(), 0);
    $this->assertDatabaseCount((new PractitionerEarning)->getTable(), 0);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 0);
    expect($participant->refresh()->payment_status)->toBe(ParticipantPaymentStatus::Unpaid);
});

it('applies partial and final refunds cumulatively to payment participant earning and cash', function () {
    [$participant, , , $mainCashbox] = createPaymentScenario(60);
    $actor = paymentActorWithPermissions('payments.create', 'refunds.create');
    $payment = app(PaymentService::class)->pay(
        $participant,
        100_000,
        0,
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );
    $service = app(RefundService::class);

    $partial = $service->refund($payment, 30_000, 'Partial return', $actor, (string) Str::uuid(), CarbonImmutable::parse('2026-09-09 12:00:00'));

    expect($partial->amount)->toBe(30_000)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($participant->refresh()->payment_status)->toBe(ParticipantPaymentStatus::PartiallyRefunded)
        ->and($payment->practitionerEarning->refresh()->amount)->toBe(42_000)
        ->and(app(CashboxService::class)->balance($mainCashbox))->toBe(70_000);

    $final = $service->refund($payment, 70_000, 'Final return', $actor, (string) Str::uuid(), CarbonImmutable::parse('2026-09-10 12:00:00'));

    expect($final->amount)->toBe(70_000)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($participant->refresh()->payment_status)->toBe(ParticipantPaymentStatus::Refunded)
        ->and($payment->practitionerEarning->refresh()->amount)->toBe(0)
        ->and(app(CashboxService::class)->balance($mainCashbox))->toBe(0);
    $this->assertDatabaseCount((new Refund)->getTable(), 2);
});

it('posts a practitioner adjustment in the current week when refunding a closed-week payment', function () {
    [$participant, $practitioner] = createPaymentScenario(60);
    $actor = paymentActorWithPermissions('payments.create', 'refunds.create');
    $payment = app(PaymentService::class)->pay(
        $participant,
        100_000,
        0,
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );
    $payment->financialWeek->update(['status' => 'closed', 'closed_at' => '2026-09-10 23:59:59']);

    $refund = app(RefundService::class)->refund(
        $payment,
        50_000,
        'Late correction',
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-15 12:00:00'),
    );
    $adjustment = FinancialAdjustment::query()->where('refund_id', $refund->id)->sole();

    expect($refund->after_closed_week)->toBeTrue()
        ->and($refund->financial_week_id)->not->toBe($payment->financial_week_id)
        ->and($adjustment->practitioner_id)->toBe($practitioner->id)
        ->and($adjustment->financial_week_id)->toBe($refund->financial_week_id)
        ->and($adjustment->amount)->toBe(-30_000)
        ->and($payment->practitionerEarning->refresh()->amount)->toBe(60_000);
});

it('uses a current-week adjustment instead of mutating an earning from another open week', function () {
    [$participant] = createPaymentScenario(60);
    $actor = paymentActorWithPermissions('payments.create', 'refunds.create');
    $payment = app(PaymentService::class)->pay(
        $participant,
        100_000,
        0,
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-08 12:00:00'),
    );

    $refund = app(RefundService::class)->refund(
        $payment,
        50_000,
        'Cross-week correction',
        $actor,
        (string) Str::uuid(),
        CarbonImmutable::parse('2026-09-11 12:00:00'),
    );
    $adjustment = FinancialAdjustment::query()->where('refund_id', $refund->id)->sole();

    expect($payment->financialWeek->status->value)->toBe('open')
        ->and($refund->after_closed_week)->toBeFalse()
        ->and($refund->financial_week_id)->not->toBe($payment->financial_week_id)
        ->and($adjustment->amount)->toBe(-30_000)
        ->and($payment->practitionerEarning->refresh()->amount)->toBe(60_000);
});

it('returns the same refund for an identical retry and rejects refunds above the remaining amount', function () {
    [$participant] = createPaymentScenario();
    $actor = paymentActorWithPermissions('payments.create', 'refunds.create');
    $payment = app(PaymentService::class)->pay($participant, 100_000, 0, $actor, (string) Str::uuid());
    $key = (string) Str::uuid();
    $service = app(RefundService::class);

    $first = $service->refund($payment, 40_000, 'Correction', $actor, $key);
    $second = $service->refund($payment, 40_000, 'Correction', $actor, $key);
    $excessive = fn () => $service->refund($payment, 60_001, 'Too much', $actor, (string) Str::uuid());

    expect($second->is($first))->toBeTrue()
        ->and($excessive)->toThrow(FinancialException::class, 'أكبر من الرصيد المتبقي');
    $this->assertDatabaseCount((new Refund)->getTable(), 1);
    $this->assertDatabaseCount((new CashboxTransaction)->getTable(), 2);
});
