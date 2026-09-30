<?php

use App\Exceptions\FinancialException;
use App\Models\FinancialAgreement;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialAgreementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function agreementActorWithPermission(string ...$permissionNames): User
{
    $role = Role::factory()->create();
    foreach ($permissionNames as $permissionName) {
        $role->permissions()->attach(Permission::factory()->create(['name' => $permissionName, 'label' => $permissionName]));
    }

    return User::factory()->for($role)->create();
}

it('resolves one practitioner agreement for its inclusive effective period', function () {
    $actor = agreementActorWithPermission('financial.agreements.manage');
    $practitioner = Practitioner::factory()->create();
    $agreement = app(FinancialAgreementService::class)->create([
        'practitioner_id' => $practitioner->id,
        'practitioner_percentage' => '62.50',
        'effective_from' => '2026-09-01',
        'effective_to' => '2026-09-30',
    ], $actor);
    $service = app(FinancialAgreementService::class);

    expect($service->effectiveFor($practitioner, '2026-09-01')->is($agreement))->toBeTrue()
        ->and($service->effectiveFor($practitioner, '2026-09-30')->is($agreement))->toBeTrue()
        ->and($service->findEffective($practitioner, '2026-10-01'))->toBeNull();
});

it('rejects overlapping active periods for the same practitioner', function () {
    $actor = agreementActorWithPermission('financial.agreements.manage');
    $practitioner = Practitioner::factory()->create();
    FinancialAgreement::factory()->create([
        'practitioner_id' => $practitioner->id,
        'effective_from' => '2026-09-01',
        'effective_to' => '2026-09-30',
        'is_active' => true,
    ]);

    $action = fn () => app(FinancialAgreementService::class)->create([
        'practitioner_id' => $practitioner->id,
        'practitioner_percentage' => 50,
        'effective_from' => '2026-09-30',
        'effective_to' => '2026-10-31',
    ], $actor);

    expect($action)->toThrow(FinancialException::class);
    $this->assertDatabaseCount((new FinancialAgreement)->getTable(), 1);
});

it('allows adjacent periods and independent practitioners', function () {
    $actor = agreementActorWithPermission('financial.agreements.manage');
    $practitioner = Practitioner::factory()->create();
    $otherPractitioner = Practitioner::factory()->create();
    FinancialAgreement::factory()->create([
        'practitioner_id' => $practitioner->id,
        'effective_from' => '2026-09-01',
        'effective_to' => '2026-09-30',
    ]);
    $service = app(FinancialAgreementService::class);
    $adjacent = $service->create([
        'practitioner_id' => $practitioner->id,
        'practitioner_percentage' => 55,
        'effective_from' => '2026-10-01',
    ], $actor);
    $independent = $service->create([
        'practitioner_id' => $otherPractitioner->id,
        'practitioner_percentage' => 65,
        'effective_from' => '2026-09-15',
    ], $actor);

    expect($adjacent->exists)->toBeTrue()->and($independent->exists)->toBeTrue();
    $this->assertDatabaseCount((new FinancialAgreement)->getTable(), 3);
});

it('requires agreement management permission without persisting a record', function () {
    $actor = agreementActorWithPermission();
    $practitioner = Practitioner::factory()->create();
    $action = fn () => app(FinancialAgreementService::class)->create([
        'practitioner_id' => $practitioner->id,
        'practitioner_percentage' => 50,
        'effective_from' => '2026-09-01',
    ], $actor);

    expect($action)->toThrow(FinancialException::class);
    $this->assertDatabaseCount((new FinancialAgreement)->getTable(), 0);
});

it('soft deletes an agreement without losing its historical identity', function () {
    $actor = agreementActorWithPermission('financial.agreements.manage');
    $agreement = FinancialAgreement::factory()->create();

    app(FinancialAgreementService::class)->delete($agreement, $actor);

    expect(FinancialAgreement::query()->find($agreement->id))->toBeNull()
        ->and(FinancialAgreement::withTrashed()->find($agreement->id)?->is_active)->toBeFalse();
});
