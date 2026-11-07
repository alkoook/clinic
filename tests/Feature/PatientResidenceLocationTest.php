<?php

use App\Livewire\Patients\Form;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function patientResidenceManager(): User
{
    $role = Role::factory()->create();
    $permissions = collect(['patients.create', 'patients.update', 'patients.view'])
        ->map(fn (string $name): Permission => Permission::factory()->create(['name' => $name]));
    $role->permissions()->attach($permissions);

    return User::factory()->for($role)->create();
}

it('stores an optional residence location when creating a patient', function () {
    $user = patientResidenceManager();
    $practitioner = Practitioner::factory()->create();

    Livewire::actingAs($user)
        ->test(Form::class)
        ->set('currentPractitionerId', $practitioner->id)
        ->set('medicalFileNumber', '27')
        ->set('fullName', 'مريض جديد')
        ->set('age', '30')
        ->set('gender', 'male')
        ->set('maritalStatus', 'single')
        ->set('residenceLocation', 'دمشق، المزة')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('patients', [
        'medical_file_number' => '27',
        'residence_location' => 'دمشق، المزة',
    ]);
});

it('updates and displays the residence location in the patient profile', function () {
    $user = patientResidenceManager();
    $patient = Patient::factory()->create(['residence_location' => null]);

    Livewire::actingAs($user)
        ->test(Form::class, ['patient' => $patient])
        ->set('residenceLocation', 'ريف دمشق')
        ->call('save')
        ->assertHasNoErrors();

    expect($patient->refresh()->residence_location)->toBe('ريف دمشق');
    $this->actingAs($user)
        ->get(route('patients.show', $patient))
        ->assertSee('مكان السكن')
        ->assertSee('ريف دمشق');
});
