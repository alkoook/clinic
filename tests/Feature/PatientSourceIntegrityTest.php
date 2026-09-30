<?php

use App\Enums\PatientSource;
use App\Livewire\Patients\Form;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('keeps the original patient source immutable when editing the patient', function () {
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::factory()->create(['name' => 'patients.update']));
    $user = User::factory()->for($role)->create();
    $referrer = Practitioner::factory()->create();
    $patient = Patient::factory()->create([
        'source' => PatientSource::Clinic,
        'referring_practitioner_id' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Form::class, ['patient' => $patient])
        ->assertSee('يُثبّت المصدر الأصلي')
        ->set('source', PatientSource::Practitioner->value)
        ->set('referringPractitionerId', $referrer->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($patient->refresh()->source)->toBe(PatientSource::Clinic)
        ->and($patient->referring_practitioner_id)->toBeNull();
});
