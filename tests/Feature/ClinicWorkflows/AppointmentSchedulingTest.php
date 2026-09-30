<?php

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\AvailabilityExceptionType;
use App\Exceptions\SchedulingException;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use App\Models\PractitionerAvailabilityException;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\TreatmentType;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(LazilyRefreshDatabase::class);

function schedulingActorWithPermissions(string ...$abilities): User
{
    $role = Role::factory()->create();
    $permissions = collect($abilities)->map(
        fn (string $ability): Permission => Permission::factory()->create(['name' => $ability]),
    );
    $role->permissions()->attach($permissions);

    return User::factory()->for($role)->create();
}

/**
 * @return array{practitioner: Practitioner, room: Room, treatmentType: TreatmentType, patient: Patient}
 */
function individualSchedulingResources(string $date = '2026-09-12'): array
{
    $practitioner = Practitioner::factory()->create();
    $room = Room::factory()->create();
    $treatmentType = TreatmentType::factory()->create(['is_group' => false]);
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);

    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => CarbonImmutable::parse($date)->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
        'effective_to' => null,
    ]);

    return compact('practitioner', 'room', 'treatmentType', 'patient');
}

it('books an individual appointment only inside the practitioner recurring availability', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();

    $appointment = app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
        'notes' => 'Initial consultation',
    ], $actor);

    expect($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->ends_at->format('Y-m-d H:i:s'))->toBe('2026-09-12 11:00:00')
        ->and($appointment->participants()->pluck('patient_id')->all())->toBe([$resources['patient']->id]);
    $this->assertDatabaseCount('treatment_sessions', 0);
    $this->assertDatabaseCount('payments', 0);
});

it('rejects appointments outside clinic hours before checking practitioner availability', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    Setting::factory()->create([
        'group' => 'clinic',
        'key' => 'clinic.working_hours',
        'type' => 'json',
        'value' => json_encode([6 => ['enabled' => true, 'start' => '11:00', 'end' => '15:00']]),
    ]);
    Cache::forget('clinic.settings');

    expect(fn () => app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor))->toThrow(SchedulingException::class, 'خارج ساعات دوام العيادة');
});

it('rejects an appointment that is not wholly inside practitioner availability', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();

    expect(fn () => app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 16:30:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor))->toThrow(
        SchedulingException::class,
        'المعالج غير متاح لكامل مدة الموعد المحدد.',
    );

    $this->assertDatabaseCount('appointments', 0);
    $this->assertDatabaseCount('appointment_participants', 0);
});

it('subtracts a partial unavailable exception from recurring availability', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    PractitionerAvailabilityException::factory()->create([
        'practitioner_id' => $resources['practitioner']->id,
        'date' => '2026-09-12',
        'type' => AvailabilityExceptionType::Unavailable,
        'start_time' => '12:00:00',
        'end_time' => '13:00:00',
    ]);

    expect(fn () => app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 11:45:00',
        'duration_minutes' => 30,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor))->toThrow(SchedulingException::class);

    $this->assertDatabaseCount('appointments', 0);
});

it('adds an exceptional availability period on a normally unavailable day', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    PractitionerAvailabilityException::factory()->available()->create([
        'practitioner_id' => $resources['practitioner']->id,
        'date' => '2026-09-13',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
    ]);

    $appointment = app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-13 10:30:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor);

    $this->assertModelExists($appointment);
});

it('rejects overlapping appointments for the same practitioner even in different rooms', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    $secondRoom = Room::factory()->create();
    $secondPatient = Patient::factory()->create([
        'current_practitioner_id' => $resources['practitioner']->id,
    ]);
    $service = app(AppointmentService::class);
    $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor);

    expect(fn () => $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $secondRoom->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:30:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$secondPatient->id],
    ], $actor))->toThrow(
        SchedulingException::class,
        'المعالج لديه موعد آخر يتداخل مع هذا الوقت.',
    );

    $this->assertDatabaseCount('appointments', 1);
});

it('rejects overlapping appointments in the same room for different practitioners', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    $secondPractitioner = Practitioner::factory()->create();
    $secondPatient = Patient::factory()->create(['current_practitioner_id' => $secondPractitioner->id]);
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $secondPractitioner->id,
        'day_of_week' => 6,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
    ]);
    $service = app(AppointmentService::class);
    $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor);

    expect(fn () => $service->book([
        'practitioner_id' => $secondPractitioner->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:30:00',
        'duration_minutes' => 30,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$secondPatient->id],
    ], $actor))->toThrow(
        SchedulingException::class,
        'الغرفة محجوزة خلال هذا الوقت.',
    );

    $this->assertDatabaseCount('appointments', 1);
});

it('allows back-to-back appointments at the same practitioner and room boundary', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    $secondPatient = Patient::factory()->create([
        'current_practitioner_id' => $resources['practitioner']->id,
    ]);
    $service = app(AppointmentService::class);
    $first = $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor);

    $second = $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 11:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$secondPatient->id],
    ], $actor);

    expect($first->ends_at->equalTo($second->starts_at))->toBeTrue();
    $this->assertDatabaseCount('appointments', 2);
});

it('books all distinct patients as participants in a group appointment', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    $groupTreatment = TreatmentType::factory()->group()->create();
    $patients = Patient::factory()->count(3)->create([
        'current_practitioner_id' => $resources['practitioner']->id,
    ]);

    $appointment = app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $groupTreatment->id,
        'starts_at' => '2026-09-12 13:00:00',
        'duration_minutes' => 90,
        'kind' => AppointmentKind::Group,
        'patient_ids' => [...$patients->modelKeys(), $patients->first()->id],
    ], $actor);

    expect($appointment->participants()->orderBy('patient_id')->pluck('patient_id')->all())
        ->toBe($patients->modelKeys());
    $this->assertDatabaseCount('appointment_participants', 3);
});

it('reschedules with explicit lineage while leaving all financial records untouched', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    $service = app(AppointmentService::class);
    $original = $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor);

    $replacement = $service->reschedule($original, [
        'starts_at' => '2026-09-12 14:00:00',
    ], $actor);

    expect($original->refresh()->status)->toBe(AppointmentStatus::Rescheduled)
        ->and($replacement->rescheduled_from_id)->toBe($original->id)
        ->and($replacement->status)->toBe(AppointmentStatus::Scheduled)
        ->and($replacement->participants()->pluck('patient_id')->all())->toBe([$resources['patient']->id]);
    $this->assertDatabaseCount('treatment_sessions', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('cashbox_transactions', 0);
});

it('keeps the original appointment scheduled when its replacement cannot be booked', function () {
    $actor = schedulingActorWithPermissions('appointments.manage');
    $resources = individualSchedulingResources();
    $otherPatient = Patient::factory()->create([
        'current_practitioner_id' => $resources['practitioner']->id,
    ]);
    $service = app(AppointmentService::class);
    $original = $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor);
    $service->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 14:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$otherPatient->id],
    ], $actor);

    expect(fn () => $service->reschedule($original, [
        'starts_at' => '2026-09-12 14:30:00',
    ], $actor))->toThrow(SchedulingException::class);

    expect($original->refresh()->status)->toBe(AppointmentStatus::Scheduled)
        ->and($original->rescheduledAppointments()->count())->toBe(0);
    $this->assertDatabaseCount('appointments', 2);
});

it('denies appointment creation when the actor lacks the management permission', function () {
    $actor = schedulingActorWithPermissions('patients.view');
    $resources = individualSchedulingResources();

    expect(fn () => app(AppointmentService::class)->book([
        'practitioner_id' => $resources['practitioner']->id,
        'room_id' => $resources['room']->id,
        'treatment_type_id' => $resources['treatmentType']->id,
        'starts_at' => '2026-09-12 10:00:00',
        'duration_minutes' => 60,
        'kind' => AppointmentKind::Individual,
        'patient_ids' => [$resources['patient']->id],
    ], $actor))->toThrow(
        SchedulingException::class,
        'لا تملك الصلاحية اللازمة لتنفيذ هذه العملية.',
    );

    $this->assertDatabaseCount('appointments', 0);
});
