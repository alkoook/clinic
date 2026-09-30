<?php

use App\Exports\ClinicReportExport;
use App\Livewire\Reports\Index as ReportIndex;
use App\Livewire\Sessions\Index as SessionIndex;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(LazilyRefreshDatabase::class);

/** @param list<string> $permissions */
function reportVisibilityUser(string $roleName, array $permissions): User
{
    $role = Role::factory()->create(['name' => $roleName]);
    $role->permissions()->sync(
        collect($permissions)
            ->map(fn (string $permission): int => Permission::factory()->create(['name' => $permission])->getKey())
            ->all(),
    );

    return User::factory()->for($role)->create();
}

it('fails closed for an own-report user without a linked practitioner', function () {
    Excel::fake();
    $user = reportVisibilityUser('unlinked-clinician', ['reports.view_own']);

    $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($user)->get(route('reports.export', ['type' => 'appointments']))->assertForbidden();
    $this->actingAs($user)->get(route('reports.export', [
        'type' => 'appointments',
        'practitioner_id' => PHP_INT_MAX,
    ]))->assertForbidden();
});

it('uses full report permission instead of a practitioner role name', function () {
    Excel::fake();
    $user = reportVisibilityUser('practitioner', ['reports.view']);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSee('المرضى');

    $this->actingAs($user)
        ->get(route('reports.export', ['type' => 'patients']))
        ->assertRedirect(route('reports.index'));
    expect($user->reportExports()->sole()->type)->toBe('patients');
});

it('scopes own reports by permission even when the role has another name', function () {
    Excel::fake();
    $user = reportVisibilityUser('clinical-reviewer', ['reports.view_own']);
    $ownPractitioner = Practitioner::factory()->for($user)->create();
    $otherPractitioner = Practitioner::factory()->create();
    $ownAppointment = Appointment::factory()->for($ownPractitioner)->create();
    $otherAppointment = Appointment::factory()->for($otherPractitioner)->create();
    AppointmentParticipant::factory()->for($ownAppointment)->create();
    AppointmentParticipant::factory()->for($otherAppointment)->create();

    Livewire::actingAs($user)
        ->test(ReportIndex::class)
        ->assertSet('reportType', 'appointments')
        ->set('practitionerId', $otherPractitioner->getKey())
        ->assertSet('practitionerId', $ownPractitioner->getKey())
        ->set('reportType', 'financial')
        ->assertSet('reportType', 'appointments');

    $this->actingAs($user)
        ->get(route('reports.export', [
            'type' => 'appointments',
            'practitioner_id' => $otherPractitioner->getKey(),
        ]))
        ->assertRedirect(route('reports.index'));
    $queuedExport = $user->reportExports()->sole();
    $export = new ClinicReportExport('appointments', $queuedExport->filters);
    $appointmentIds = $export->query()->pluck('id');
    expect($appointmentIds)->toContain($ownAppointment->getKey())->not->toContain($otherAppointment->getKey());
});

it('does not expose the clinic share in an own practitioner report or export', function () {
    Excel::fake();
    $user = reportVisibilityUser('clinician', ['reports.view_own', 'sessions.view_own']);
    $practitioner = Practitioner::factory()->for($user)->create();
    $treatmentSession = TreatmentSession::factory()
        ->for($practitioner)
        ->create([
            'started_at' => now(),
            'ended_at' => now()->addHour(),
            'practitioner_name' => $practitioner->name,
        ]);
    TreatmentSessionParticipant::factory()
        ->for($treatmentSession)
        ->create([
            'original_price' => 100_000,
            'discount_amount' => 0,
            'final_amount' => 100_000,
            'practitioner_share' => 61_337,
            'clinic_share' => 38_663,
        ]);

    Livewire::actingAs($user)
        ->test(ReportIndex::class)
        ->set('reportType', 'sessions')
        ->assertSee('حصة المعالج')
        ->assertSee('61,337')
        ->assertDontSee('حصة العيادة')
        ->assertDontSee('38,663');

    $this->actingAs($user)
        ->get(route('reports.export', ['type' => 'sessions']))
        ->assertRedirect(route('reports.index'));
    $queuedExport = $user->reportExports()->sole();
    $export = new ClinicReportExport('sessions', $queuedExport->filters);
    $mappedRow = $export->map($export->query()->firstOrFail());
    expect($export->headings())->toContain('حصة الطبيب')->not->toContain('حصة العيادة')
        ->and($mappedRow)->toContain(61_337)->not->toContain(38_663);
});

it('hides practitioner percentages and shares from secretaries on session screens', function () {
    $this->seed(RolePermissionSeeder::class);
    $administrator = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();
    $secretary = User::factory()->for(Role::query()->where('name', 'secretary')->sole())->create();
    $practitionerUser = User::factory()->for(Role::query()->where('name', 'practitioner')->sole())->create();
    $practitioner = Practitioner::factory()->for($practitionerUser)->create();
    $treatmentSession = TreatmentSession::factory()
        ->for($practitioner)
        ->create([
            'started_at' => now(),
            'ended_at' => now()->addHour(),
            'practitioner_name' => $practitioner->name,
        ]);
    $participant = TreatmentSessionParticipant::factory()
        ->for($treatmentSession)
        ->paid()
        ->create([
            'original_price' => 100_000,
            'discount_amount' => 0,
            'final_amount' => 100_000,
            'practitioner_percentage' => 41.23,
            'practitioner_share' => 41_234,
            'clinic_share' => 58_766,
        ]);
    Payment::factory()
        ->for($participant, 'treatmentSessionParticipant')
        ->create(['amount' => 100_000]);

    Livewire::actingAs($secretary)
        ->test(SessionIndex::class)
        ->assertDontSee('حصة المعالج')
        ->assertDontSee('41,234');
    Livewire::actingAs($secretary)
        ->test(SessionShow::class, ['treatmentSession' => $treatmentSession])
        ->assertDontSee('نسبة المعالج')
        ->assertDontSee('حصة المعالج')
        ->assertDontSee('41,234');

    Livewire::actingAs($practitionerUser)
        ->test(SessionIndex::class)
        ->assertSee('حصة المعالج')
        ->assertSee('41,234');
    Livewire::actingAs($practitionerUser)
        ->test(SessionShow::class, ['treatmentSession' => $treatmentSession])
        ->assertSee('نسبة المعالج')
        ->assertSee('حصة المعالج')
        ->assertSee('41,234');

    Livewire::actingAs($administrator)
        ->test(SessionIndex::class)
        ->assertSee('حصة المعالج')
        ->assertSee('41,234');
    Livewire::actingAs($administrator)
        ->test(SessionShow::class, ['treatmentSession' => $treatmentSession])
        ->assertSee('نسبة المعالج')
        ->assertSee('حصة المعالج')
        ->assertSee('41,234');
});

it('binds formula-like untrusted report text as literal spreadsheet strings', function () {
    $formulaName = '=HYPERLINK("https://example.test","click")';
    $formulaPhone = '+963999999999';
    $formulaHospital = " \t@SUM(1,1)";

    Patient::factory()->create([
        'medical_file_number' => '-1+1',
        'full_name' => $formulaName,
        'phone' => $formulaPhone,
        'incoming_hospital_name' => $formulaHospital,
    ]);

    $contents = Excel::raw(new ClinicReportExport('patients', []), ExcelWriter::XLSX);
    $temporaryFile = tempnam(sys_get_temp_dir(), 'clinic-report-');
    expect($temporaryFile)->not->toBeFalse();

    try {
        file_put_contents($temporaryFile, $contents);
        $spreadsheet = IOFactory::load($temporaryFile);
        $sheet = $spreadsheet->getActiveSheet();

        expect($sheet->getCell('A8')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('A8')->getValue())->toBe('-1+1')
            ->and($sheet->getCell('B8')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('B8')->getValue())->toBe($formulaName)
            ->and($sheet->getCell('C8')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('C8')->getValue())->toBe($formulaPhone)
            ->and($sheet->getCell('F8')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('F8')->getValue())->toBe($formulaHospital);

        $spreadsheet->disconnectWorksheets();
    } finally {
        unlink($temporaryFile);
    }
});
