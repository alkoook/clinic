<?php

use App\Enums\AppointmentStatus;
use App\Enums\ParticipantPaymentStatus;
use App\Enums\TreatmentSessionStatus;
use App\Livewire\Appointments\Calendar;
use App\Livewire\Appointments\Index as AppointmentIndex;
use App\Livewire\Sessions\Index as SessionIndex;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('renders every appointment status with its semantic color icon and arabic label', function (
    AppointmentStatus $status,
    string $class,
) {
    $html = Blade::render('<x-ui.status-badge :status="$status" />', compact('status'));

    expect($status->badgeClass())->toBe($class)
        ->and($html)->toContain('status-badge '.$class)
        ->and($html)->toContain($status->label())
        ->and($html)->toContain('<svg');
})->with([
    'scheduled blue' => [AppointmentStatus::Scheduled, 'status-info'],
    'attended green' => [AppointmentStatus::Attended, 'status-success'],
    'no show red' => [AppointmentStatus::NoShow, 'status-danger'],
    'cancelled neutral' => [AppointmentStatus::Cancelled, 'status-neutral'],
    'rescheduled amber' => [AppointmentStatus::Rescheduled, 'status-warning'],
    'completed teal' => [AppointmentStatus::Completed, 'status-completed'],
]);

it('renders every administrative session status with its semantic presentation', function (
    TreatmentSessionStatus $status,
    string $class,
) {
    $html = Blade::render('<x-ui.status-badge :status="$status" />', compact('status'));

    expect($status->badgeClass())->toBe($class)
        ->and($html)->toContain('status-badge '.$class)
        ->and($html)->toContain($status->label())
        ->and($html)->toContain('<svg');
})->with([
    'awaiting payment amber' => [TreatmentSessionStatus::PendingPayment, 'status-warning'],
    'ready green' => [TreatmentSessionStatus::Ready, 'status-success'],
    'completed teal' => [TreatmentSessionStatus::Completed, 'status-completed'],
    'refunded purple' => [TreatmentSessionStatus::Refunded, 'status-refunded'],
    'cancelled neutral' => [TreatmentSessionStatus::Cancelled, 'status-neutral'],
]);

it('shows matching status presentation across appointment and session screens', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-09-09 14:00:00'));
    $user = User::factory()->for(Role::query()->where('name', 'secretary')->sole())->create();
    $patient = Patient::factory()->create();
    $appointment = Appointment::factory()->create([
        'status' => AppointmentStatus::Attended,
        'starts_at' => now(),
        'ends_at' => now()->addHour(),
    ]);
    $appointment->participants()->create([
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Attended,
    ]);
    $session = TreatmentSession::factory()->create([
        'status' => TreatmentSessionStatus::Refunded,
        'started_at' => now()->subMinutes(30),
        'ended_at' => null,
        'completed_at' => null,
    ]);
    TreatmentSessionParticipant::factory()->for($session)->for($patient)->create([
        'payment_status' => ParticipantPaymentStatus::Refunded,
    ]);

    Livewire::actingAs($user)
        ->test(AppointmentIndex::class)
        ->assertSeeHtml('status-surface status-success')
        ->assertSeeHtml('status-badge status-success')
        ->assertSee(AppointmentStatus::Attended->label());

    Livewire::actingAs($user)
        ->test(Calendar::class)
        ->assertSeeHtml('status-surface status-success')
        ->assertSeeHtml('status-badge status-success');

    Livewire::actingAs($user)
        ->test(SessionIndex::class)
        ->assertSeeHtml('status-surface status-info')
        ->assertSeeHtml('status-badge status-info')
        ->assertSeeHtml('status-badge status-refunded')
        ->assertSee('قيد العلاج')
        ->assertSee(TreatmentSessionStatus::Refunded->label());

    Livewire::actingAs($user)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->assertSeeHtml('status-heading status-info')
        ->assertSeeHtml('status-badge status-info')
        ->assertSeeHtml('status-badge status-refunded');
});
