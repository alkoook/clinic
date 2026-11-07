<?php

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

it('downloads an authorized private patient attachment', function () {
    Storage::fake('local');
    $role = Role::factory()->create();
    $role->permissions()->attach([
        Permission::factory()->create(['name' => 'patients.view']),
        Permission::factory()->create(['name' => 'patient_attachments.view']),
        Permission::factory()->create(['name' => 'patient_attachments.download']),
    ]);
    $user = User::factory()->for($role)->create();
    $patient = Patient::factory()->create();
    $attachment = PatientAttachment::factory()->for($patient)->create([
        'disk' => 'local',
        'path' => 'patient-attachments/private-result.pdf',
        'original_name' => 'private-result.pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('local')->put($attachment->path, 'private medical contents');

    $response = $this->actingAs($user)->get(route('patients.attachments.download', [$patient, $attachment]));

    $response->assertDownload('private-result.pdf');
});

it('returns 404 when an attachment is requested through another patient record', function () {
    Storage::fake('local');
    $role = Role::factory()->create();
    $role->permissions()->attach([
        Permission::factory()->create(['name' => 'patients.view']),
        Permission::factory()->create(['name' => 'patient_attachments.view']),
    ]);
    $user = User::factory()->for($role)->create();
    $owningPatient = Patient::factory()->create();
    $differentPatient = Patient::factory()->create();
    $attachment = PatientAttachment::factory()->for($owningPatient)->create([
        'disk' => 'local',
        'path' => 'patient-attachments/nested-boundary.pdf',
    ]);
    Storage::disk('local')->put($attachment->path, 'private medical contents');

    $response = $this->actingAs($user)->get(route('patients.attachments.download', [$differentPatient, $attachment]));

    $response->assertNotFound();
});

it('forbids downloading a private attachment without download permission', function () {
    Storage::fake('local');
    $role = Role::factory()->create();
    $role->permissions()->attach([
        Permission::factory()->create(['name' => 'patients.view']),
        Permission::factory()->create(['name' => 'patient_attachments.view']),
    ]);
    $user = User::factory()->for($role)->create();
    $patient = Patient::factory()->create();
    $attachment = PatientAttachment::factory()->for($patient)->create([
        'disk' => 'local',
        'path' => 'patient-attachments/restricted.pdf',
    ]);
    Storage::disk('local')->put($attachment->path, 'private medical contents');

    $response = $this->actingAs($user)->get(route('patients.attachments.download', [$patient, $attachment]));

    $response->assertForbidden();
});
