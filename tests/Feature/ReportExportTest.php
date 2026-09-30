<?php

use App\Jobs\CompleteReportExport;
use App\Jobs\PrepareReportExport;
use App\Livewire\Reports\Index;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ClinicEventNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

uses(LazilyRefreshDatabase::class);

it('queues an Arabic named filtered report without blocking the request', function () {
    Queue::fake();
    $role = Role::factory()->create(['name' => 'report-admin']);
    $role->permissions()->attach(Permission::factory()->create(['name' => 'reports.view']));
    $user = User::factory()->for($role)->create();

    Livewire::actingAs($user)->test(Index::class)
        ->set('reportType', 'patients')
        ->set('dateFrom', '2026-09-01')
        ->set('dateTo', '2026-09-09')
        ->set('source', 'clinic')
        ->call('requestExport')
        ->assertHasNoErrors();

    $export = $user->reportExports()->sole();
    expect($export->download_name)->toBe('تقرير_المرضى_من_2026-09-01_إلى_2026-09-09.xlsx')
        ->and($export->filters['source'])->toBe('clinic')
        ->and($export->status)->toBe('pending');
    Queue::assertPushed(PrepareReportExport::class, fn (PrepareReportExport $job): bool => $job->reportExportId === $export->id && $job->queue === 'exports');
});

it('allows only the owner or a full reports administrator to download a ready export', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Storage::disk('local')->put('reports/ready.xlsx', 'xlsx');
    $export = ReportExport::factory()->for($owner, 'requester')->create([
        'status' => 'completed',
        'file_path' => 'reports/ready.xlsx',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($stranger)->get(route('reports.exports.download', $export))->assertForbidden();
    $response = $this->actingAs($owner)->get(route('reports.exports.download', $export))->assertOk();
    expect($response->headers->get('content-disposition'))->toContain("filename*=utf-8''".rawurlencode($export->download_name));
});

it('marks a queued export ready and notifies its requester', function () {
    Notification::fake();
    $user = User::factory()->create();
    $export = ReportExport::factory()->for($user, 'requester')->create(['status' => 'processing']);

    (new CompleteReportExport($export->id))->handle();

    expect($export->fresh()->status)->toBe('completed')
        ->and($export->fresh()->expires_at)->not->toBeNull();
    Notification::assertSentTo($user, ClinicEventNotification::class);
});

it('records the filtered row count before queuing the workbook', function () {
    Excel::fake();
    $user = User::factory()->create();
    Patient::factory()->count(3)->create();
    $export = ReportExport::factory()->for($user, 'requester')->create([
        'type' => 'patients',
        'filters' => ['type' => 'patients'],
        'status' => 'pending',
    ]);

    (new PrepareReportExport($export->id))->handle();

    expect($export->fresh()->row_count)->toBe(3)
        ->and($export->fresh()->status)->toBe('processing');
});
