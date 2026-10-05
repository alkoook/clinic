<?php

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\ParticipantPaymentStatus;
use App\Enums\PatientSource;
use App\Enums\TreatmentSessionStatus;
use App\Exceptions\SchedulingException;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use App\Models\Role;
use App\Models\Room;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\TreatmentSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('binds session attachment uploads to the only participant automatically', function () {
    $actor = sessionWorkflowActor('sessions.manage');
    $session = TreatmentSession::factory()->create();
    $patient = Patient::factory()->create();
    TreatmentSessionParticipant::factory()->for($session)->for($patient)->create();

    Livewire::actingAs($actor)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->assertSet('attachmentPatientId', $patient->id);
});

it('replaces the session attachment submit control when selected files change', function () {
    $actor = sessionWorkflowActor('sessions.manage', 'patients.view', 'patient_attachments.upload');
    $session = TreatmentSession::factory()->create();
    $patient = Patient::factory()->create();
    TreatmentSessionParticipant::factory()->for($session)->for($patient)->create();

    Livewire::actingAs($actor)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->assertSeeHtml('wire:key="session-attachment-submit-0"')
        ->set('attachmentFiles', [UploadedFile::fake()->create('session.pdf', 10, 'application/pdf')])
        ->assertSeeHtml('wire:key="session-attachment-submit-1"');
});

it('limits group session attachment uploads to the sessions own participants', function () {
    Storage::fake('local');
    $actor = sessionWorkflowActor('sessions.manage', 'patient_attachments.upload');
    $appointment = Appointment::factory()->group()->create();
    $session = TreatmentSession::factory()->for($appointment)->create();
    $firstPatient = Patient::factory()->create(['full_name' => 'ليان الخطيب']);
    $secondPatient = Patient::factory()->create(['full_name' => 'نور الحسن']);
    $unrelatedPatient = Patient::factory()->create(['full_name' => 'مريض غير مرتبط']);
    TreatmentSessionParticipant::factory()->for($session)->for($firstPatient)->create();
    TreatmentSessionParticipant::factory()->for($session)->for($secondPatient)->create();

    Livewire::actingAs($actor)
        ->test(SessionShow::class, ['treatmentSession' => $session])
        ->assertSee($firstPatient->full_name)
        ->assertSee($secondPatient->full_name)
        ->assertDontSee($unrelatedPatient->full_name)
        ->set('attachmentPatientId', $unrelatedPatient->id)
        ->set('attachmentFiles', [UploadedFile::fake()->create('report.pdf', 10, 'application/pdf')])
        ->call('uploadAttachments')
        ->assertHasErrors([
            'attachmentPatientId' => 'المريض المحدد ليس من المشاركين في هذه الجلسة.',
        ]);

    $this->assertDatabaseCount('patient_attachments', 0);
});

function sessionWorkflowActor(string ...$abilities): User
{
    $role = Role::factory()->create();
    $permissions = collect($abilities)->map(
        fn (string $ability): Permission => Permission::factory()->create(['name' => $ability]),
    );
    $role->permissions()->attach($permissions);

    return User::factory()->for($role)->create();
}

it('keeps a booked appointment separate from an actual treatment session', function () {
    $actor = sessionWorkflowActor('appointments.manage');
    $practitioner = Practitioner::factory()->create();
    $room = Room::factory()->create();
    $treatmentType = TreatmentType::factory()->create();
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 6,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
    ]);

    $appointment = app(AppointmentService::class)->book([
        'practitioner_id' => $practitioner->id,
        'room_id' => $room->id,
        'treatment_type_id' => $treatmentType->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$patient->id],
    ], $actor);

    expect($appointment->treatmentSession)->toBeNull()
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled);
    $this->assertDatabaseCount('treatment_sessions', 0);
    $this->assertDatabaseCount('treatment_session_participants', 0);
});

it('creates immutable session and participant snapshots from a group appointment', function () {
    $actor = sessionWorkflowActor('appointments.manage', 'sessions.manage');
    $practitioner = Practitioner::factory()->create(['name' => 'Dr Original']);
    $referrer = Practitioner::factory()->create(['name' => 'Dr Referrer']);
    $room = Room::factory()->create(['name' => 'Room Original']);
    $treatmentType = TreatmentType::factory()->group()->create(['name' => 'Group Therapy']);
    $clinicPatient = Patient::factory()->create([
        'full_name' => 'Clinic Patient',
        'source' => PatientSource::Clinic,
        'current_practitioner_id' => $practitioner->id,
    ]);
    $referredPatient = Patient::factory()->practitionerSourced($referrer)->create([
        'full_name' => 'Referred Patient',
        'current_practitioner_id' => $practitioner->id,
    ]);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 6,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
    ]);
    $appointment = app(AppointmentService::class)->book([
        'practitioner_id' => $practitioner->id,
        'room_id' => $room->id,
        'treatment_type_id' => $treatmentType->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Group,
        'patient_ids' => [$clinicPatient->id, $referredPatient->id],
    ], $actor);
    $participants = $appointment->participants()->get()->keyBy('patient_id');
    $this->travelTo(CarbonImmutable::parse('2026-09-12 10:00:00'));

    $session = app(TreatmentSessionService::class)->createFromAppointment($appointment, [
        $participants[$clinicPatient->id]->id => ['original_price' => 100_000, 'discount_amount' => 10_000],
        $participants[$referredPatient->id]->id => 80_000,
    ], $actor);

    expect($session->status)->toBe(TreatmentSessionStatus::PendingPayment)
        ->and($session->practitioner_name)->toBe('Dr Original')
        ->and($session->room_name)->toBe('Room Original')
        ->and($session->treatment_type_name)->toBe('Group Therapy')
        ->and($appointment->refresh()->status)->toBe(AppointmentStatus::Attended);
    $this->assertDatabaseHas('treatment_session_participants', [
        'treatment_session_id' => $session->id,
        'patient_id' => $clinicPatient->id,
        'patient_name' => 'Clinic Patient',
        'patient_source' => PatientSource::Clinic->value,
        'source_practitioner_id' => null,
        'original_price' => 100_000,
        'discount_amount' => 10_000,
        'final_amount' => 90_000,
        'payment_status' => ParticipantPaymentStatus::Unpaid->value,
    ]);
    $this->assertDatabaseHas('treatment_session_participants', [
        'treatment_session_id' => $session->id,
        'patient_id' => $referredPatient->id,
        'patient_name' => 'Referred Patient',
        'patient_source' => PatientSource::Practitioner->value,
        'source_practitioner_id' => $referrer->id,
        'original_price' => 80_000,
        'discount_amount' => 0,
        'final_amount' => 80_000,
    ]);

    $practitioner->update(['name' => 'Dr Changed']);
    $room->update(['name' => 'Room Changed']);
    $treatmentType->update(['name' => 'Treatment Changed']);
    $clinicPatient->update(['full_name' => 'Patient Changed']);
    $session->refresh();

    expect($session->practitioner_name)->toBe('Dr Original')
        ->and($session->room_name)->toBe('Room Original')
        ->and($session->treatment_type_name)->toBe('Group Therapy')
        ->and($session->participants()->where('patient_id', $clinicPatient->id)->value('patient_name'))
        ->toBe('Clinic Patient');
});

it('returns the existing session when the same appointment is converted twice', function () {
    $actor = sessionWorkflowActor('sessions.manage');
    $practitioner = Practitioner::factory()->create();
    $appointment = Appointment::factory()->create([
        'practitioner_id' => $practitioner->id,
        'starts_at' => '2026-09-09 14:00:00',
        'ends_at' => '2026-09-09 15:00:00',
        'status' => AppointmentStatus::Scheduled,
    ]);
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    $appointmentParticipant = $appointment->participants()->create([
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Scheduled,
    ]);
    $this->travelTo($appointment->starts_at);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id, 'day_of_week' => 3,
        'start_time' => '09:00', 'end_time' => '18:00', 'effective_from' => null,
    ]);
    $service = app(TreatmentSessionService::class);
    $first = $service->createFromAppointment($appointment, [
        $appointmentParticipant->id => 50_000,
    ], $actor);

    $second = $service->createFromAppointment($appointment, [
        $appointmentParticipant->id => 90_000,
    ], $actor);

    expect($second->is($first))->toBeTrue()
        ->and($second->participants()->sole()->original_price)->toBe(50_000);
    $this->assertDatabaseCount('treatment_sessions', 1);
    $this->assertDatabaseCount('treatment_session_participants', 1);
});

it('rolls back session creation when any participant price is missing', function () {
    $actor = sessionWorkflowActor('sessions.manage');
    $appointment = Appointment::factory()->group()->create([
        'status' => AppointmentStatus::Scheduled,
    ]);
    $patients = Patient::factory()->count(2)->create([
        'current_practitioner_id' => $appointment->practitioner_id,
    ]);
    $firstParticipant = $appointment->participants()->create([
        'patient_id' => $patients[0]->id,
        'status' => AppointmentStatus::Scheduled,
    ]);
    $appointment->participants()->create([
        'patient_id' => $patients[1]->id,
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect(fn () => app(TreatmentSessionService::class)->createFromAppointment($appointment, [
        $firstParticipant->id => 50_000,
    ], $actor))->toThrow(SchedulingException::class);

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->participants()->pluck('status')->all())->each->toBe(AppointmentStatus::Scheduled);
    $this->assertDatabaseCount('treatment_sessions', 0);
    $this->assertDatabaseCount('treatment_session_participants', 0);
});

it('denies session creation when the actor lacks the management permission', function () {
    $actor = sessionWorkflowActor('appointments.manage');
    $appointment = Appointment::factory()->create();
    $patient = Patient::factory()->create([
        'current_practitioner_id' => $appointment->practitioner_id,
    ]);
    $participant = $appointment->participants()->create([
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect(fn () => app(TreatmentSessionService::class)->createFromAppointment($appointment, [
        $participant->id => 50_000,
    ], $actor))->toThrow(
        SchedulingException::class,
        'لا تملك الصلاحية اللازمة لتنفيذ هذه العملية.',
    );

    $this->assertDatabaseCount('treatment_sessions', 0);
});

it('rejects early attendance outside the practitioner working hours', function () {
    $actor = sessionWorkflowActor('sessions.manage');
    $appointment = Appointment::factory()->create([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
        'status' => AppointmentStatus::Scheduled,
    ]);
    $patient = Patient::factory()->create(['current_practitioner_id' => $appointment->practitioner_id]);
    $participant = $appointment->participants()->create([
        'patient_id' => $patient->id,
        'status' => AppointmentStatus::Scheduled,
    ]);

    expect(fn () => app(TreatmentSessionService::class)->createFromAppointment($appointment, [
        $participant->id => 50_000,
    ], $actor))->toThrow(SchedulingException::class, 'المعالج غير متاح');

    $this->assertDatabaseCount('treatment_sessions', 0);
});

it('creates group session records only for attendees and marks the rest as no show', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-09 14:00:00'));
    $actor = sessionWorkflowActor('sessions.manage');
    $appointment = Appointment::factory()->group()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now(),
        'status' => AppointmentStatus::Scheduled,
    ]);
    $patients = Patient::factory()->count(2)->create([
        'current_practitioner_id' => $appointment->practitioner_id,
    ]);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $appointment->practitioner_id, 'day_of_week' => 3,
        'start_time' => '09:00', 'end_time' => '18:00', 'effective_from' => null,
    ]);
    $attendee = $appointment->participants()->create([
        'patient_id' => $patients[0]->id,
        'status' => AppointmentStatus::Scheduled,
    ]);
    $noShow = $appointment->participants()->create([
        'patient_id' => $patients[1]->id,
        'status' => AppointmentStatus::Scheduled,
    ]);

    $session = app(TreatmentSessionService::class)->createFromAppointment(
        $appointment,
        [$attendee->id => 50_000],
        $actor,
        [$attendee->id],
    );

    expect($session->participants()->sole()->patient_id)->toBe($patients[0]->id)
        ->and($attendee->refresh()->status)->toBe(AppointmentStatus::Attended)
        ->and($noShow->refresh()->status)->toBe(AppointmentStatus::NoShow);
});

it('does not complete a session whose payment was partially refunded', function () {
    $actor = sessionWorkflowActor('sessions.manage');
    $session = TreatmentSession::factory()->create([
        'status' => TreatmentSessionStatus::PendingPayment,
    ]);
    TreatmentSessionParticipant::factory()->for($session)->create([
        'payment_status' => ParticipantPaymentStatus::PartiallyRefunded,
    ]);

    expect(fn () => app(TreatmentSessionService::class)->complete($session, $actor))
        ->toThrow(SchedulingException::class, 'قبل تسجيل دفعات جميع المشاركين');

    expect($session->refresh()->status)->toBe(TreatmentSessionStatus::PendingPayment);
});
