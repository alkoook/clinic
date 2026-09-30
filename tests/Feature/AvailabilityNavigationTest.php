<?php

use App\Livewire\Availability\Manage;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClinicReferenceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('keeps the practitioner availability route parameterized and renders it for authorized managers', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $administrator = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();
    $practitioner = Practitioner::factory()->create();

    expect(Practitioner::query()->find($practitioner->getKey()))->not->toBeNull();

    $this->actingAs($administrator)
        ->get(route('availability.manage', $practitioner))
        ->assertOk()
        ->assertSeeLivewire(Manage::class);

    $navigation = $this->actingAs($administrator)->get(route('dashboard'))->assertOk()->getContent();

    expect($navigation)->toContain('href="'.route('practitioners.index').'"')
        ->and($navigation)->not->toContain('href="'.route('availability.mine').'"');
});

it('generates a practitioner own-availability link with its required practitioner parameter', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $practitionerUser = User::factory()->for(Role::query()->where('name', 'practitioner')->sole())->create();
    $practitioner = Practitioner::factory()->for($practitionerUser)->create();
    $expectedUrl = route('availability.manage', $practitioner);

    $response = $this->actingAs($practitionerUser)->get(route('dashboard'))->assertOk();

    $response->assertSee('href="'.$expectedUrl.'"', false)
        ->assertDontSee('href="'.route('availability.mine').'"', false);
    $this->actingAs($practitionerUser)->get($expectedUrl)->assertOk()->assertSeeLivewire(Manage::class);
    $this->actingAs($practitionerUser)->get(route('availability.mine'))->assertRedirect($expectedUrl);
});

it('allows secretary read access but protects availability mutations', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $secretary = User::factory()->for(Role::query()->where('name', 'secretary')->sole())->create();
    $administrator = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();
    $practitioner = Practitioner::factory()->create();

    $this->actingAs($secretary)->get(route('availability.mine'))->assertRedirectToRoute('availability.index');
    $this->get(route('availability.index'))->assertOk();
    $this->get(route('availability.manage', $practitioner))->assertOk();
    Livewire::test(Manage::class, ['practitioner' => $practitioner])->call('addPeriod')->assertForbidden();
    $this->actingAs($administrator)->get(route('availability.mine'))->assertRedirectToRoute('availability.index');
});
