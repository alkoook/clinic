<?php

use App\Enums\ParticipantPaymentStatus;
use App\Enums\TreatmentSessionStatus;
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

it('rejects patient attachments larger than 25 megabytes with an arabic error', function () {
    Storage::fake('local');
    $user = attachmentUploadUser();
    $patient = Patient::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(PatientShow::class, ['patient' => $patient])
        ->set('files', [UploadedFile::fake()->create('large.pdf', 25_601, 'application/pdf')])
        ->assertHasErrors(['files.0']);

    expect($component->errors()->first('files.0'))->toContain('حجم')->toContain('25')
        ->and($patient->attachments()->count())->toBe(0);
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
    $session = TreatmentSession::factory()->ready()->create();
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->paid()->create([
        'original_price' => 120_000,
        'discount_amount' => 20_000,
        'final_amount' => 100_000,
        'practitioner_percentage' => 40,
        'practitioner_share' => 40_000,
        'clinic_share' => 60_000,
    ]);

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
        ->and($attachment->disk)->toBe('local')
        ->and($session->refresh()->status)->toBe(TreatmentSessionStatus::Ready)
        ->and($session->completed_at)->toBeNull()
        ->and($participant->refresh()->original_price)->toBe(120_000)
        ->and($participant->discount_amount)->toBe(20_000)
        ->and($participant->final_amount)->toBe(100_000)
        ->and($participant->practitioner_share)->toBe(40_000)
        ->and($participant->clinic_share)->toBe(60_000)
        ->and($participant->payment_status)->toBe(ParticipantPaymentStatus::Paid);

    Storage::disk('local')->assertExists($attachment->path);
});

it('shows session attachment safety errors without changing session or financial state', function () {
    Storage::fake('local');
    $user = attachmentUploadUser();
    $patient = Patient::factory()->create();
    $session = TreatmentSession::factory()->ready()->create();
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->paid()->create();
    $sessionFields = ['status', 'ended_at', 'completed_at'];
    $financialFields = [
        'original_price',
        'discount_amount',
        'final_amount',
        'practitioner_percentage',
        'practitioner_share',
        'clinic_share',
        'payment_status',
    ];
    $sessionState = collect($session->getRawOriginal())->only($sessionFields)->all();
    $financialState = collect($participant->getRawOriginal())->only($financialFields)->all();

    Livewire::actingAs($user)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->set('attachmentPatientId', $patient->id)
        ->set('attachmentFiles', [
            UploadedFile::fake()->createWithContent('report.pdf', '<?php echo "unsafe";')->mimeType('text/x-php'),
        ])
        ->call('uploadAttachments')
        ->assertHasErrors([
            'attachmentFiles' => 'محتوى الملف لا يطابق نوعه أو أن الملف غير آمن.',
        ]);

    expect($patient->attachments()->count())->toBe(0)
        ->and(collect($session->refresh()->getRawOriginal())->only($sessionFields)->all())->toBe($sessionState)
        ->and(collect($participant->refresh()->getRawOriginal())->only($financialFields)->all())->toBe($financialState);
});

it('uploads a private attachment after session completion without reopening or changing finances', function () {
    Storage::fake('local');
    $this->travelTo('2026-09-20 15:00:00');

    $user = attachmentUploadUser();
    $patient = Patient::factory()->create();
    $session = TreatmentSession::factory()->completed()->create([
        'started_at' => now()->subHour(),
        'ended_at' => now()->subMinutes(20),
        'completed_at' => now()->subMinutes(10),
    ]);
    $participant = TreatmentSessionParticipant::factory()->for($session)->for($patient)->paid()->create([
        'original_price' => 150_000,
        'discount_amount' => 10_000,
        'final_amount' => 140_000,
        'practitioner_percentage' => 50,
        'practitioner_share' => 70_000,
        'clinic_share' => 70_000,
    ]);
    $sessionFields = ['status', 'started_at', 'ended_at', 'completed_at'];
    $financialFields = [
        'original_price',
        'discount_amount',
        'final_amount',
        'practitioner_percentage',
        'practitioner_share',
        'clinic_share',
        'payment_status',
    ];
    $sessionState = collect($session->getRawOriginal())->only($sessionFields)->all();
    $financialState = collect($participant->getRawOriginal())->only($financialFields)->all();

    Livewire::actingAs($user)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->set('attachmentPatientId', $patient->id)
        ->set('attachmentFiles', [UploadedFile::fake()->create('aftercare.pdf', 40, 'application/pdf')])
        ->call('uploadAttachments')
        ->assertHasNoErrors();

    $attachment = $patient->attachments()->sole();

    expect($attachment->treatment_session_id)->toBe($session->id)
        ->and($attachment->disk)->toBe('local')
        ->and(collect($session->refresh()->getRawOriginal())->only($sessionFields)->all())->toBe($sessionState)
        ->and(collect($participant->refresh()->getRawOriginal())->only($financialFields)->all())->toBe($financialState);

    Storage::disk('local')->assertExists($attachment->path);
    Storage::disk('public')->assertMissing($attachment->path);
});
