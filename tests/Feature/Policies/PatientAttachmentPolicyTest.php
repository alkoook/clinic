<?php

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use App\Policies\PatientAttachmentPolicy;
use App\Policies\PatientPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function patientAttachmentPolicyUser(array $permissions): User
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

it('allows a global patient viewer with attachment permission to view an attachment', function () {
    $user = patientAttachmentPolicyUser(['patients.view', 'patient_attachments.view']);
    $patient = Patient::factory()->create();
    $attachment = PatientAttachment::factory()->for($patient)->create();

    $policy = app(PatientAttachmentPolicy::class);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $attachment))->toBeTrue();
});

it('allows a practitioner to view attachments belonging to their patient only', function () {
    $user = patientAttachmentPolicyUser(['patients.view_own', 'patient_attachments.view']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $ownPatient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    $otherPatient = Patient::factory()->create();
    $ownAttachment = PatientAttachment::factory()->for($ownPatient)->create();
    $otherAttachment = PatientAttachment::factory()->for($otherPatient)->create();

    $policy = app(PatientAttachmentPolicy::class);

    expect($policy->view($user, $ownAttachment))->toBeTrue()
        ->and($policy->view($user, $otherAttachment))->toBeFalse();
});

it('does not expose attachments without the attachment permission', function () {
    $user = patientAttachmentPolicyUser(['patients.view']);
    $attachment = PatientAttachment::factory()->create();

    $policy = app(PatientAttachmentPolicy::class);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $attachment))->toBeFalse();
});

it('does not expose attachments without access to patient records', function () {
    $user = patientAttachmentPolicyUser(['patient_attachments.view']);
    $attachment = PatientAttachment::factory()->create();

    $policy = app(PatientAttachmentPolicy::class);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->view($user, $attachment))->toBeFalse();
});

it('requires upload permission and patient access before accepting an attachment', function () {
    $user = patientAttachmentPolicyUser(['patients.view_own', 'patient_attachments.upload']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $ownPatient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    $otherPatient = Patient::factory()->create();

    $patientPolicy = app(PatientPolicy::class);

    expect($patientPolicy->uploadAttachment($user, $ownPatient))->toBeTrue()
        ->and($patientPolicy->uploadAttachment($user, $otherPatient))->toBeFalse();

    $viewer = patientAttachmentPolicyUser(['patients.view']);

    expect($patientPolicy->uploadAttachment($viewer, $ownPatient))->toBeFalse();
});

it('does not permit attachment mutation or deletion without a dedicated capability', function () {
    $user = patientAttachmentPolicyUser(['patients.view', 'patient_attachments.view', 'patient_attachments.upload']);
    $attachment = PatientAttachment::factory()->create();

    $policy = app(PatientAttachmentPolicy::class);

    expect($policy->update($user, $attachment))->toBeFalse()
        ->and($policy->delete($user, $attachment))->toBeFalse()
        ->and($policy->restore($user, $attachment))->toBeFalse()
        ->and($policy->forceDelete($user, $attachment))->toBeFalse();
});
