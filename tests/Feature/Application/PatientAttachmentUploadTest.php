<?php

use App\Livewire\Patients\Show as PatientShow;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

function attachmentUploadUser(): User
{
    $role = Role::factory()->create(['name' => 'secretary-upload']);
    $role->permissions()->sync(collect([
        'patients.view',
        'patient_attachments.view',
        'patient_attachments.upload',
        'sessions.manage',
    ])->map(fn (string $name): int => Permission::query()->firstOrCreate(
        ['name' => $name],
        ['label' => $name, 'group' => 'patients'],
    )->id));

    return User::factory()->for($role)->create();
}

it('stores pdf jpg and docx attachments privately when title description and session are empty', function (string $filename, string $mime) {
    Storage::fake('local');
    $this->travelTo('2026-09-20 15:00:00');
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('b', $length));

    $user = attachmentUploadUser();
    $patient = Patient::factory()->create(['full_name' => 'أحمد محمد']);
    $file = str_ends_with($filename, '.jpg')
        ? UploadedFile::fake()->image($filename)
        : UploadedFile::fake()->create($filename, 80, $mime);

    Livewire::actingAs($user)
        ->test(PatientShow::class, ['patient' => $patient])
        ->set('attachmentTitle', '')
        ->set('attachmentDescription', '')
        ->set('treatmentSessionId', null)
        ->set('files', [$file])
        ->call('storeAttachments')
        ->assertHasNoErrors()
        ->assertDispatched('app-toast');

    $attachment = $patient->attachments()->sole();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);

    expect($attachment->title)->toBe($filename)
        ->and($attachment->description)->toBeNull()
        ->and($attachment->treatment_session_id)->toBeNull()
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->path)->toStartWith('patients/'.$patient->id.'/attachments/')
        ->and($attachment->stored_name)->toBe('أحمد-محمد_2026-09-20_bbbbbb.'.$extension);

    Storage::disk('local')->assertExists($attachment->path);
    Storage::disk('public')->assertMissing($attachment->path);
})->with([
    'pdf' => ['تقرير.pdf', 'application/pdf'],
    'jpg' => ['صورة.jpg', 'image/jpeg'],
    'docx' => ['ملاحظات.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
]);

it('keeps the upload action off the reserved livewire upload alias and enables submit after a file is selected', function () {
    $user = attachmentUploadUser();
    $patient = Patient::factory()->create();

    Livewire::actingAs($user)
        ->test(PatientShow::class, ['patient' => $patient])
        ->assertSeeHtml('wire:submit.prevent="storeAttachments"')
        ->assertDontSeeHtml('wire:submit="upload"')
        ->assertSeeHtml('disabled')
        ->set('files', [UploadedFile::fake()->create('lab.pdf', 20, 'application/pdf')])
        ->assertSee('lab.pdf');
});

it('shows an arabic error for executable uploads and does not store the file', function () {
    Storage::fake('local');
    $user = attachmentUploadUser();
    $patient = Patient::factory()->create();

    Livewire::actingAs($user)
        ->test(PatientShow::class, ['patient' => $patient])
        ->set('files', [UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream')])
        ->call('storeAttachments')
        ->assertHasErrors(['files.0' => 'نوع الملف غير مسموح.']);

    expect($patient->attachments()->count())->toBe(0);
});

it('rejects a dangerous payload even when it uses an allowed document extension', function () {
    Storage::fake('local');
    $user = attachmentUploadUser();
    $patient = Patient::factory()->create();

    Livewire::actingAs($user)
        ->test(PatientShow::class, ['patient' => $patient])
        ->set('files', [UploadedFile::fake()->createWithContent('report.pdf', '<?php echo "unsafe";')->mimeType('text/x-php')])
        ->call('storeAttachments')
        ->assertHasErrors(['files' => 'محتوى الملف لا يطابق نوعه أو أن الملف غير آمن.']);

    expect($patient->attachments()->count())->toBe(0)
        ->and(config('filesystems.disks.local.serve'))->toBeFalse();
});

it('includes the session number in the private stored filename when a session is linked', function () {
    Storage::fake('local');
    $this->travelTo('2026-09-20 15:00:00');
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('c', $length));

    $user = attachmentUploadUser();
    $patient = Patient::factory()->create(['full_name' => 'أحمد محمد']);
    $session = TreatmentSession::factory()->create();
    TreatmentSessionParticipant::factory()->for($session)->for($patient)->create();

    Livewire::actingAs($user)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->set('attachmentPatientId', $patient->id)
        ->set('attachmentFiles', [UploadedFile::fake()->create('جلسة.pdf', 40, 'application/pdf')])
        ->call('uploadAttachments')
        ->assertHasNoErrors();

    $attachment = $patient->attachments()->sole();

    expect($attachment->title)->toBe('جلسة.pdf')
        ->and($attachment->treatment_session_id)->toBe($session->id)
        ->and($attachment->stored_name)->toBe('أحمد-محمد_جلسة-'.$session->id.'_2026-09-20_cccccc.pdf')
        ->and($attachment->disk)->toBe('local');

    Storage::disk('local')->assertExists($attachment->path);
});
