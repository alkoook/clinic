<?php

use App\Exports\ClinicReportExport;
use App\Jobs\PrepareReportExport;
use App\Livewire\Finance\AuditLog;
use App\Models\FinancialAuditLog;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('filters the financial timeline by action actor entity amount and period', function () {
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::factory()->create(['name' => 'audit.view', 'label' => 'عرض السجل']));
    $viewer = User::factory()->for($role)->create();
    $actor = User::factory()->create(['name' => 'منفذ العملية']);
    $payment = Payment::factory()->create();
    FinancialAuditLog::factory()->create([
        'user_id' => $actor->id,
        'action' => 'payment.created',
        'auditable_type' => Payment::class,
        'auditable_id' => $payment->id,
        'after' => ['amount' => 150000],
        'created_at' => '2026-09-10 10:00:00',
    ]);
    FinancialAuditLog::factory()->create(['action' => 'expense.created', 'after' => ['amount' => 50000]]);

    Livewire::actingAs($viewer)->test(AuditLog::class)
        ->set('action', 'payment.created')
        ->set('userId', $actor->id)
        ->set('entityType', Payment::class)
        ->set('amountMin', 100000)
        ->set('amountMax', 200000)
        ->set('dateFrom', '2026-09-10')
        ->set('dateTo', '2026-09-10')
        ->assertSee('تسجيل دفعة')
        ->assertSee('منفذ العملية')
        ->assertSeeHtml('<strong>1</strong>');
});

it('denies the audit timeline without its server side permission', function () {
    Livewire::actingAs(User::factory()->create())->test(AuditLog::class)->assertForbidden();
});

it('queues an Arabic audit workbook with exactly the active filters', function () {
    Queue::fake();
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::factory()->create(['name' => 'audit.view']));
    $viewer = User::factory()->for($role)->create();
    FinancialAuditLog::factory()->create(['action' => 'payment.created', 'created_at' => '2026-09-10 10:00:00']);
    FinancialAuditLog::factory()->create(['action' => 'expense.created', 'created_at' => '2026-09-10 11:00:00']);

    Livewire::actingAs($viewer)->test(AuditLog::class)
        ->set('action', 'payment.created')
        ->set('dateFrom', '2026-09-10')
        ->set('dateTo', '2026-09-10')
        ->call('requestExport')
        ->assertHasNoErrors();

    $request = $viewer->reportExports()->sole();
    expect($request->type)->toBe('audit')
        ->and($request->download_name)->toBe('تقرير_سجل_التدقيق_من_2026-09-10_إلى_2026-09-10.xlsx')
        ->and((new ClinicReportExport('audit', $request->filters))->query()->count())->toBe(1);
    Queue::assertPushed(PrepareReportExport::class, fn (PrepareReportExport $job): bool => $job->reportExportId === $request->id);
});
