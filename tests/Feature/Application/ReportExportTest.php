<?php

use App\Exports\ClinicReportExport;
use App\Livewire\Reports\Index as ReportIndex;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

it('returns an XLSX patient report to an authorized administrator', function () {
    $role = Role::factory()->create(['name' => 'super-admin']);
    $role->permissions()->attach(Permission::factory()->create(['name' => 'reports.view']));
    $administrator = User::factory()->for($role)->create();
    Patient::factory()->create(['full_name' => 'Exported Patient']);
    $this->travelTo(CarbonImmutable::parse('2026-09-08 13:45:10'));

    $response = $this->actingAs($administrator)->get(route('reports.export', ['type' => 'patients']));

    $response->assertRedirect(route('reports.index'));
    $this->assertDatabaseHas('report_exports', [
        'requested_by' => $administrator->id,
        'type' => 'patients',
        'download_name' => 'تقرير_المرضى_2026-09-08-134510.xlsx',
    ]);
});

it('forbids a practitioner from exporting a financial report', function () {
    Excel::fake();
    $role = Role::factory()->create(['name' => 'practitioner']);
    $role->permissions()->attach(Permission::factory()->create(['name' => 'reports.view_own']));
    $practitionerUser = User::factory()->for($role)->create();
    Practitioner::factory()->for($practitionerUser)->create();

    $response = $this->actingAs($practitionerUser)->get(route('reports.export', [
        'type' => 'financial',
    ]));

    $response->assertForbidden();
});

it('forces a tampered practitioner page filter back to the signed in practitioner', function () {
    $role = Role::factory()->create(['name' => 'practitioner']);
    $role->permissions()->attach(Permission::factory()->create(['name' => 'reports.view_own']));
    $practitionerUser = User::factory()->for($role)->create();
    $ownPractitioner = Practitioner::factory()->for($practitionerUser)->create();
    $otherPractitioner = Practitioner::factory()->create();

    Livewire::actingAs($practitionerUser)
        ->test(ReportIndex::class)
        ->set('reportType', 'appointments')
        ->set('practitionerId', $otherPractitioner->id)
        ->assertSet('practitionerId', $ownPractitioner->id);
});

it('overrides a tampered practitioner export filter with the signed in practitioner', function () {
    Excel::fake();
    $role = Role::factory()->create(['name' => 'practitioner']);
    $role->permissions()->attach(Permission::factory()->create(['name' => 'reports.view_own']));
    $practitionerUser = User::factory()->for($role)->create();
    $ownPractitioner = Practitioner::factory()->for($practitionerUser)->create();
    $otherPractitioner = Practitioner::factory()->create();
    $ownAppointment = Appointment::factory()->for($ownPractitioner)->create();
    $otherAppointment = Appointment::factory()->for($otherPractitioner)->create();
    AppointmentParticipant::factory()->for($ownAppointment)->create();
    AppointmentParticipant::factory()->for($otherAppointment)->create();
    $this->travelTo(CarbonImmutable::parse('2026-09-08 13:45:10'));

    $response = $this->actingAs($practitionerUser)->get(route('reports.export', [
        'type' => 'appointments',
        'practitioner_id' => $otherPractitioner->id,
    ]));

    $response->assertRedirect(route('reports.index'));
    $queuedExport = $practitionerUser->reportExports()->sole();
    $export = new ClinicReportExport('appointments', $queuedExport->filters);
    $appointmentIds = $export->query()->pluck('id');
    expect($queuedExport->filters['practitioner_id'])->toBe($ownPractitioner->id)
        ->and($appointmentIds)->toContain($ownAppointment->id)
        ->not->toContain($otherAppointment->id);
});
