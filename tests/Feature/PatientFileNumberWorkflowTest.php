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

function patientFileNumberManager(): User
{
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::factory()->create(['name' => 'patients.create']));

    return User::factory()->for($role)->create();
}

/** @return array<string, string|int> */
function requiredPatientFormData(Practitioner $practitioner, string $fileNumber): array
{
    return [
        'currentPractitionerId' => $practitioner->id,
        'medicalFileNumber' => $fileNumber,
        'fullName' => 'أحمد',
        'age' => 32,
        'gender' => 'male',
        'maritalStatus' => 'married',
    ];
}

it('suggests the next medical file number for the selected practitioner', function () {
    $user = patientFileNumberManager();
    $practitioner = Practitioner::factory()->create();
    Patient::factory()->create(['current_practitioner_id' => $practitioner->id, 'medical_file_number' => '4']);
    Patient::factory()->create(['current_practitioner_id' => $practitioner->id, 'medical_file_number' => '11']);

    Livewire::actingAs($user)
        ->test(Form::class)
        ->set('currentPractitionerId', $practitioner->id)
        ->assertSet('medicalFileNumber', '12');
});

it('allows the same medical file number for different practitioners', function () {
    $user = patientFileNumberManager();
    $firstPractitioner = Practitioner::factory()->create();
    $secondPractitioner = Practitioner::factory()->create();
    Patient::factory()->create(['current_practitioner_id' => $firstPractitioner->id, 'medical_file_number' => '5']);

    Livewire::actingAs($user)
        ->test(Form::class)
        ->set(requiredPatientFormData($secondPractitioner, '5'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('patients', [
        'current_practitioner_id' => $secondPractitioner->id,
        'medical_file_number' => '5',
    ]);
});

it('rejects a duplicate medical file number for the same practitioner', function () {
    $user = patientFileNumberManager();
    $practitioner = Practitioner::factory()->create();
    Patient::factory()->create(['current_practitioner_id' => $practitioner->id, 'medical_file_number' => '5']);

    Livewire::actingAs($user)
        ->test(Form::class)
        ->set(requiredPatientFormData($practitioner, '5'))
        ->call('save')
        ->assertHasErrors(['medicalFileNumber' => 'unique']);
});

it('requires the new patient workflow to contain the requested core data', function () {
    $user = patientFileNumberManager();

    Livewire::actingAs($user)
        ->test(Form::class)
        ->set('fullName', '')
        ->call('save')
        ->assertHasErrors([
            'currentPractitionerId' => 'required',
            'medicalFileNumber' => 'required',
            'fullName' => 'required',
            'age' => 'required',
            'gender' => 'required',
            'maritalStatus' => 'required',
        ]);
});
