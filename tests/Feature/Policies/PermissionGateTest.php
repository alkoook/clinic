<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

it('defines every catalog permission as a role-backed gate', function () {
    $permissions = config('clinic.permissions');
    $role = Role::factory()->create();

    $permissionIds = collect($permissions)
        ->map(function (array $metadata, string $ability): int {
            return Permission::factory()->create([
                'name' => $ability,
                'label' => $metadata['label'],
                'group' => $metadata['group'],
            ])->id;
        });

    $role->permissions()->sync($permissionIds);

    $authorizedUser = User::factory()->for($role)->create();
    $unauthorizedUser = User::factory()->create();

    foreach (array_keys($permissions) as $ability) {
        expect(Gate::forUser($authorizedUser)->allows($ability))
            ->toBeTrue("Expected the catalog ability [{$ability}] to be granted through the user's role.")
            ->and(Gate::forUser($unauthorizedUser)->allows($ability))
            ->toBeFalse("Expected the catalog ability [{$ability}] to be denied without a role permission.");
    }
});

it('denies every ability to an inactive user even when their role grants it', function () {
    $ability = 'patients.view';
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => $ability]);
    $role->permissions()->attach($permission);

    $user = User::factory()->inactive()->for($role)->create();

    expect(Gate::forUser($user)->allows($ability))->toBeFalse();
});
