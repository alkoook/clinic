<?php

use App\Livewire\Sessions\Show;
use App\Models\Cashbox;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use App\Services\FinancialWeekService;
use App\Services\WeeklyClosingService;
use App\Support\ArabicFormat;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function operationalActor(string ...$permissions): User
{
    $role = Role::factory()->create();
    $role->permissions()->attach(collect($permissions)->map(fn (string $permission): Permission => Permission::factory()->create(['name' => $permission])));

    return User::factory()->for($role)->create();
}

it('formats appointment dates with an Arabic weekday and fixed Syrian currency', function () {
    expect(ArabicFormat::appointmentDate('2026-09-10 14:00:00'))->toBe('الخميس 10/09/2026')
        ->and(ArabicFormat::money(150000))->toBe('150,000 ل.س');
});

it('opens a new manual period and safely reopens a closing without financial effects', function () {
    $actor = operationalActor('cashboxes.manage');
    Cashbox::factory()->main()->create();
    Cashbox::factory()->side()->create();
    $periods = app(FinancialWeekService::class);
    $period = $periods->openNew($actor, CarbonImmutable::parse('2026-09-08 09:00:00'));
    $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00:00'));
    app(WeeklyClosingService::class)->close($period, $actor, (string) Str::uuid());

    expect($period->refresh()->status->value)->toBe('closed');

    $reopened = app(WeeklyClosingService::class)->reopen($period, $actor);

    expect($reopened->status->value)->toBe('open')
        ->and($reopened->closing)->toBeNull();
    $this->assertDatabaseHas('financial_audit_logs', ['action' => 'financial_week.reopened']);
});

it('offers the latest prior real session price and applies it only on request', function () {
    $actor = operationalActor('sessions.manage', 'payments.create');
    $patient = Patient::factory()->create();
    $oldSession = TreatmentSession::factory()->create(['started_at' => now()->subWeek()]);
    TreatmentSessionParticipant::factory()->for($oldSession)->for($patient)->create([
        'original_price' => 150_000,
        'discount_amount' => 10_000,
        'final_amount' => 140_000,
    ]);
    $currentSession = TreatmentSession::factory()->create(['started_at' => now()]);
    $currentParticipant = TreatmentSessionParticipant::factory()->for($currentSession)->for($patient)->create([
        'original_price' => 90_000,
        'discount_amount' => 0,
        'final_amount' => 90_000,
    ]);

    Livewire::actingAs($actor)
        ->test(Show::class, ['treatmentSession' => $currentSession])
        ->assertSee('150,000')
        ->call('usePreviousPrice', $currentParticipant->id)
        ->assertSet("originalPrices.{$currentParticipant->id}", 150_000)
        ->assertSet("discountAmounts.{$currentParticipant->id}", 0);

    expect($currentParticipant->refresh()->original_price)->toBe(90_000);
});

it('shows a calm empty previous-price state when the patient has no earlier session', function () {
    $actor = operationalActor('sessions.manage', 'payments.create');
    $session = TreatmentSession::factory()->create(['started_at' => now()]);
    TreatmentSessionParticipant::factory()->for($session)->create();

    Livewire::actingAs($actor)
        ->test(Show::class, ['treatmentSession' => $session])
        ->assertSee('لا يوجد سعر سابق لهذا المريض.');
});
