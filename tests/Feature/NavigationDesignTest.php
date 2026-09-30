<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('selects exactly one navigation item and marks its containing group', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();

    $response = $this->actingAs($user)->get(route('appointments.index'))->assertOk();
    $html = $response->getContent();

    expect(substr_count($html, 'aria-current="page"'))->toBe(2)
        ->and($html)->toContain('data-active="true"')
        ->and(collect(config('clinic.navigation'))->flatMap(fn (array $group) => $group['items'])->where('route', 'dashboard')->count())->toBe(1);
});
