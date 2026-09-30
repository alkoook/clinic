<?php

use App\Exceptions\FinancialException;
use App\Models\FinancialWeek;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialAdjustmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

function financialAdjustmentActor(): User
{
    $role = Role::factory()->create(['name' => 'super-admin']);
    $role->permissions()->attach(Permission::factory()->create(['name' => 'cashboxes.manage']));

    return User::factory()->for($role)->create();
}

it('records a manual adjustment once with an idempotency key', function () {
    $actor = financialAdjustmentActor();
    $week = FinancialWeek::factory()->create(['status' => 'open']);
    $practitioner = Practitioner::factory()->create();
    $key = (string) Str::uuid();
    $service = app(FinancialAdjustmentService::class);

    $first = $service->record($week, $practitioner, -25_000, 'تصحيح مستحق', $actor, $key);
    $second = $service->record($week, $practitioner, -25_000, 'تصحيح مستحق', $actor, $key);

    expect($second->is($first))->toBeTrue()
        ->and($first->amount)->toBe(-25_000);
    $this->assertDatabaseCount('financial_adjustments', 1);
});

it('rejects a manual adjustment against a closed week', function () {
    $actor = financialAdjustmentActor();
    $week = FinancialWeek::factory()->closed()->create();

    expect(fn () => app(FinancialAdjustmentService::class)->record(
        $week,
        null,
        10_000,
        'تصحيح عيادة',
        $actor,
        (string) Str::uuid(),
    ))->toThrow(FinancialException::class, 'أسبوع مغلق');

    $this->assertDatabaseCount('financial_adjustments', 0);
});

it('keeps a posted manual adjustment immutable', function () {
    $actor = financialAdjustmentActor();
    $adjustment = app(FinancialAdjustmentService::class)->record(
        FinancialWeek::factory()->create(['status' => 'open']),
        null,
        10_000,
        'تصحيح عيادة',
        $actor,
        (string) Str::uuid(),
    );

    expect(fn () => $adjustment->update(['amount' => 20_000]))
        ->toThrow(FinancialException::class, 'حركة صندوق نهائية');
});
