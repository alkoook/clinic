<?php

use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClinicReferenceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('uses the shared Arabic themed paginator and floating navigation bar', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $administrator = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();
    Patient::factory()->count(20)->create(['created_by' => $administrator->id]);

    $response = $this->actingAs($administrator)->get(route('patients.index'));

    $response->assertOk()
        ->assertSee('app-topbar sticky z-30', false)
        ->assertSee('clinic-pagination', false)
        ->assertSee('الصفحة التالية')
        ->assertSee('عرض')
        ->assertDontSee('Showing')
        ->assertDontSee('results');
});
