<?php

use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use App\Policies\PatientPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function patientPolicyUser(array $permissions): User
{
    $role = Role::factory()->create();
    $permissionIds = collect($permissions)
        ->map(fn (string $permission): int => Permission::factory()->create([
            'name' => $permission,
            'label' => $permission,
        ])->id);

    $role->permissions()->sync($permissionIds);

    return User::factory()->for($role)->create();
}

it('allows a global patient viewer to view any patient', function () {
    $user = patientPolicyUser(['patients.view']);
    $patient = Patient::factory()->create();

    $policy = app(PatientPolicy::class);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $patient))->toBeTrue();
});

it('allows an own-patient viewer to see a currently assigned patient', function () {
    $user = patientPolicyUser(['patients.view_own']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);

    expect(app(PatientPolicy::class)->view($user, $patient))->toBeTrue();
});

it('allows an own-patient viewer to see a patient they originally referred', function () {
    $user = patientPolicyUser(['patients.view_own']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $patient = Patient::factory()->create([
        'current_practitioner_id' => null,
        'referring_practitioner_id' => $practitioner->id,
    ]);

    expect(app(PatientPolicy::class)->view($user, $patient))->toBeTrue();
});

it('allows an own-patient viewer to see a patient through an appointment', function () {
    $user = patientPolicyUser(['patients.view_own']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $patient = Patient::factory()->create([
        'current_practitioner_id' => null,
        'referring_practitioner_id' => null,
    ]);
    $appointment = Appointment::factory()->for($practitioner)->create();
    AppointmentParticipant::factory()->for($appointment)->for($patient)->create();

    expect(app(PatientPolicy::class)->view($user, $patient))->toBeTrue();
});

it('allows an own-patient viewer to see a patient through a treatment session', function () {
    $user = patientPolicyUser(['patients.view_own']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $patient = Patient::factory()->create([
        'current_practitioner_id' => null,
        'referring_practitioner_id' => null,
    ]);
    $appointment = Appointment::factory()->for($practitioner)->create();
    $session = TreatmentSession::factory()->for($appointment)->create();
    TreatmentSessionParticipant::factory()->for($session)->for($patient)->create();

    expect(app(PatientPolicy::class)->view($user, $patient))->toBeTrue();
});

it('denies an own-patient viewer access to an unrelated patient', function () {
    $user = patientPolicyUser(['patients.view_own']);
    Practitioner::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    expect(app(PatientPolicy::class)->view($user, $patient))->toBeFalse();
});

it('requires explicit permissions to create and update patients', function () {
    $user = patientPolicyUser(['patients.create']);
    $patient = Patient::factory()->create();

    $policy = app(PatientPolicy::class);

    expect($policy->create($user))->toBeTrue()
        ->and($policy->update($user, $patient))->toBeFalse();

    $updater = patientPolicyUser(['patients.update']);

    expect($policy->create($updater))->toBeFalse()
        ->and($policy->update($updater, $patient))->toBeTrue();
});

it('denies patient access when the user has no patient permission', function () {
    $user = patientPolicyUser([]);
    $patient = Patient::factory()->create();

    $policy = app(PatientPolicy::class);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $patient))->toBeFalse();
});

it('fails closed when an own-patient viewer has no linked practitioner profile', function () {
    $user = patientPolicyUser(['patients.view_own']);
    $unassignedPatient = Patient::factory()->create([
        'current_practitioner_id' => null,
        'referring_practitioner_id' => null,
    ]);

    $policy = app(PatientPolicy::class);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $unassignedPatient))->toBeFalse();
});
