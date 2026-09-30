<?php

use App\Livewire\Appointments\Index as AppointmentIndex;
use App\Livewire\Patients\Show as PatientShow;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function handoverUser(string $roleName, array $permissions): User
{
    $role = Role::factory()->create(['name' => $roleName]);
    $role->permissions()->sync(collect($permissions)->map(fn (string $name): int => Permission::query()->firstOrCreate(['name' => $name], ['label' => $name, 'group' => 'patients'])->id));

    return User::factory()->for($role)->create();
}

it('searches appointments by an Arabic fragment in the patient name through the database query', function () {
    $user = handoverUser('secretary', ['appointments.manage']);
    $matchingPatient = Patient::factory()->create(['full_name' => 'ليان الخطيب']);
    $otherPatient = Patient::factory()->create(['full_name' => 'محمد العلي']);
    $matchingAppointment = Appointment::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $otherAppointment = Appointment::factory()->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    AppointmentParticipant::factory()->for($matchingAppointment)->for($matchingPatient)->create();
    AppointmentParticipant::factory()->for($otherAppointment)->for($otherPatient)->create();

    Livewire::actingAs($user)->test(AppointmentIndex::class)
        ->set('search', 'الخطي')
        ->assertSee('ليان الخطيب')
        ->assertDontSee('محمد العلي');
});

it('allows secretary multi upload to private storage but denies preview and download', function () {
    Storage::fake('local');
    $secretary = handoverUser('secretary', ['patients.view', 'patient_attachments.view', 'patient_attachments.upload']);
    $patient = Patient::factory()->create(['full_name' => 'أحمد محمد']);

    Livewire::actingAs($secretary)->test(PatientShow::class, ['patient' => $patient])
        ->set('files', [
            UploadedFile::fake()->image('صورة.jpg'),
            UploadedFile::fake()->create('تقرير.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('بيانات.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->call('storeAttachments')
        ->assertHasNoErrors();

    expect($patient->attachments()->count())->toBe(3);
    $attachment = $patient->attachments()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->path)->toStartWith('patients/'.$patient->id.'/attachments/')
        ->and($attachment->stored_name)->toContain('أحمد-محمد_')
        ->and($attachment->stored_name)->not->toContain('ملف-عام')
        ->and($attachment->path)->not->toContain('..');

    $this->actingAs($secretary)->get(route('patients.attachments.preview', [$patient, $attachment]))->assertForbidden();
    $this->actingAs($secretary)->get(route('patients.attachments.download', [$patient, $attachment]))->assertForbidden();
});

it('rejects executable and disguised executable attachments with an Arabic error', function () {
    Storage::fake('local');
    $secretary = handoverUser('secretary', ['patients.view', 'patient_attachments.view', 'patient_attachments.upload']);
    $patient = Patient::factory()->create();

    Livewire::actingAs($secretary)->test(PatientShow::class, ['patient' => $patient])
        ->set('files', [UploadedFile::fake()->create('invoice.php.pdf', 10, 'application/pdf')])
        ->call('storeAttachments')
        ->assertHasErrors('files');

    $this->assertDatabaseCount('patient_attachments', 0);
});

it('authorizes admin and scoped practitioner previews and downloads while blocking other doctors', function () {
    Storage::fake('local');
    $admin = handoverUser('super-admin-test', ['patients.view', 'patient_attachments.view', 'patient_attachments.preview', 'patient_attachments.download']);
    $doctor = handoverUser('practitioner', ['patients.view_own', 'patient_attachments.view', 'patient_attachments.preview', 'patient_attachments.download']);
    $otherDoctor = handoverUser('practitioner-other', ['patients.view_own', 'patient_attachments.view', 'patient_attachments.preview', 'patient_attachments.download']);
    $practitioner = Practitioner::factory()->for($doctor)->create();
    Practitioner::factory()->for($otherDoctor)->create();
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    $attachment = PatientAttachment::factory()->for($patient)->create([
        'path' => 'patients/'.$patient->id.'/attachments/result.pdf',
        'stored_name' => 'result.pdf',
        'original_name' => 'نتيجة الفحص.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
    ]);
    Storage::disk('local')->put($attachment->path, '%PDF-1.4 test');

    foreach ([$admin, $doctor] as $user) {
        $this->actingAs($user)->get(route('patients.attachments.preview', [$patient, $attachment]))->assertOk();
        $response = $this->actingAs($user)->get(route('patients.attachments.download', [$patient, $attachment]))->assertOk();
        expect($response->headers->get('content-disposition'))->toContain(rawurlencode('نتيجة الفحص.pdf'));
    }

    $this->actingAs($otherDoctor)->get(route('patients.attachments.preview', [$patient, $attachment]))->assertForbidden();
    $this->actingAs($otherDoctor)->get(route('patients.attachments.download', [$patient, $attachment]))->assertForbidden();
    auth()->logout();
    $this->get(route('patients.attachments.download', [$patient, $attachment]))->assertRedirect(route('login'));
});
