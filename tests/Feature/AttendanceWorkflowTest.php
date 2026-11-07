<?php

use App\Enums\AppointmentStatus;
use App\Enums\ParticipantPaymentStatus;
use App\Livewire\Appointments\Index;
use App\Livewire\Sessions\Show;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PractitionerAvailability;
use App\Models\Role;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\TreatmentSessionService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-09-09 13:55:00'));
    $this->secretary = User::factory()->for(Role::where('name', 'secretary')->sole())->create();
    $this->appointment = Appointment::factory()->create([
        'starts_at' => now()->setTime(14, 0), 'ends_at' => now()->setTime(15, 0),
        'duration_minutes' => 60, 'status' => AppointmentStatus::Scheduled,
    ]);
    $this->appointment->participants()->create(['patient_id' => Patient::factory()->create()->id, 'status' => AppointmentStatus::Scheduled]);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $this->appointment->practitioner_id, 'day_of_week' => 3,
        'start_time' => '09:00', 'end_time' => '18:00', 'effective_from' => null,
    ]);
    $this->actingAs($this->secretary);
});

it('starts early once and navigates to the same session without changing the reservation', function () {
    $component = Livewire::test(Index::class)->call('markStatus', $this->appointment->id, 'attended');
    $session = TreatmentSession::sole();
    $component->assertHasNoErrors()->assertRedirect(route('sessions.show', $session));
    expect($session->started_at->format('H:i'))->toBe('13:55')
        ->and($session->ended_at)->toBeNull()
        ->and($this->appointment->refresh()->starts_at->format('H:i'))->toBe('14:00')
        ->and($this->appointment->ends_at->format('H:i'))->toBe('15:00');
    Livewire::test(Index::class)->call('markStatus', $this->appointment->id, 'attended')
        ->assertRedirect(route('sessions.show', $session));
    expect(TreatmentSession::count())->toBe(1);
});

it('rejects an early start while a resource is occupied', function (string $resource) {
    TreatmentSession::factory()->create([
        $resource => $this->appointment->$resource, 'started_at' => now()->subHour(), 'ended_at' => null,
    ]);
    Livewire::test(Index::class)->call('markStatus', $this->appointment->id, 'attended')->assertHasErrors('operation');
    expect($this->appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($this->appointment->treatmentSession)->toBeNull();
})->with(['practitioner_id', 'room_id']);

it('allows attendance more than thirty minutes early when resources are available', function () {
    $this->appointment->update([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);

    Livewire::test(Index::class)
        ->call('markStatus', $this->appointment->id, 'attended')
        ->assertHasNoErrors();

    expect($this->appointment->refresh()->status)->toBe(AppointmentStatus::Attended)
        ->and($this->appointment->treatmentSession->started_at->eq(now()))->toBeTrue();
});

it('orders appointments by reservation time descending', function () {
    $later = Appointment::factory()->create(['starts_at' => now()->setTime(17, 0), 'ends_at' => now()->setTime(18, 0)]);
    $component = Livewire::test(Index::class);
    expect($component->instance()->appointments->first()->id)->toBe($later->id);
});

it('orders sessions by actual start rather than creation identifier', function () {
    $latest = TreatmentSession::factory()->create(['started_at' => now(), 'ended_at' => null]);
    TreatmentSession::factory()->create(['started_at' => now()->subHour(), 'ended_at' => now()->subMinutes(5)]);
    expect(Livewire::test(App\Livewire\Sessions\Index::class)->instance()->sessions->first()->id)->toBe($latest->id);
});

it('offers booking periods after subtracting occupied room time', function () {
    $periods = app(AvailabilityService::class)->bookingIntervals(
        $this->appointment->practitioner_id, '2026-09-09', $this->appointment->room_id,
    );
    expect(array_map(fn (array $period): string => $period['start']->format('H:i').'-'.$period['end']->format('H:i'), $periods))
        ->toBe(['09:00-14:00', '15:00-18:00']);
});

it('rejects early attendance overlapping another reserved resource', function (string $resource) {
    Appointment::factory()->create([
        $resource => $this->appointment->$resource,
        'starts_at' => now()->setTime(13, 0), 'ends_at' => now()->setTime(14, 0),
        'status' => AppointmentStatus::Scheduled,
    ]);
    Livewire::test(Index::class)->call('markStatus', $this->appointment->id, 'attended')->assertHasErrors('operation');
    expect(TreatmentSession::count())->toBe(0);
})->with(['practitioner_id', 'room_id']);

it('records the actual end once when a paid session is completed', function () {
    $session = TreatmentSession::factory()->create(['started_at' => now()->subMinutes(40), 'ended_at' => null]);
    TreatmentSessionParticipant::factory()->for($session)->create(['payment_status' => ParticipantPaymentStatus::Paid]);
    $service = app(TreatmentSessionService::class);
    $service->complete($session, $this->secretary);
    expect($session->refresh()->ended_at->eq(now()))->toBeTrue();
    $endedAt = $session->ended_at->copy();
    $this->travel(5)->minutes();
    $service->complete($session, $this->secretary);
    expect($session->refresh()->ended_at->eq($endedAt))->toBeTrue();
});

it('records a five minute session independently of the booked duration', function () {
    $scheduledStart = $this->appointment->starts_at->copy();
    $scheduledEnd = $this->appointment->ends_at->copy();
    $session = TreatmentSession::factory()->for($this->appointment)->create([
        'started_at' => now(),
        'ended_at' => null,
    ]);
    TreatmentSessionParticipant::factory()->for($session)->create([
        'payment_status' => ParticipantPaymentStatus::Paid,
    ]);
    $this->travel(5)->minutes();

    app(TreatmentSessionService::class)->complete($session, $this->secretary);

    expect($session->refresh()->started_at->diffInMinutes($session->ended_at))->toBe(5.0)
        ->and($this->appointment->refresh()->starts_at->eq($scheduledStart))->toBeTrue()
        ->and($this->appointment->ends_at->eq($scheduledEnd))->toBeTrue();
});

it('keeps a long session running across the next appointment and shows a due warning', function () {
    $nextPatient = Patient::factory()->create(['full_name' => 'مريض الموعد التالي']);
    $nextAppointment = Appointment::factory()->create([
        'practitioner_id' => $this->appointment->practitioner_id,
        'room_id' => $this->appointment->room_id,
        'starts_at' => now()->setTime(15, 0),
        'ends_at' => now()->setTime(15, 30),
        'duration_minutes' => 30,
        'status' => AppointmentStatus::Scheduled,
    ]);
    $nextAppointment->participants()->create([
        'patient_id' => $nextPatient->id,
        'status' => AppointmentStatus::Scheduled,
    ]);
    Livewire::test(Index::class)->call('markStatus', $this->appointment->id, 'attended');
    $session = $this->appointment->treatmentSession()->sole();
    $session->participants()->update(['payment_status' => ParticipantPaymentStatus::Paid]);
    $this->travelTo(now()->setTime(15, 5));

    Livewire::test(Show::class, ['treatmentSession' => $session])
        ->assertSee('حان موعد الحجز التالي')
        ->assertSee($nextPatient->full_name)
        ->assertSee('لن تُنهى تلقائيًا');

    expect($session->refresh()->ended_at)->toBeNull()
        ->and($nextAppointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);

    app(TreatmentSessionService::class)->complete($session, $this->secretary);

    expect($session->refresh()->started_at->diffInMinutes($session->ended_at))->toBe(70.0)
        ->and($nextAppointment->refresh()->status)->toBe(AppointmentStatus::Scheduled);
});
