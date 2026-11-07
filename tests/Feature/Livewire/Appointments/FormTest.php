<?php

use App\Enums\AppointmentKind;
use App\Livewire\Appointments\Form;
use App\Models\Patient;
use App\Models\Permission;
use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\TreatmentType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('loads the configured duration and keeps partially typed values stable', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => 'appointments.manage']);
    $role->permissions()->attach($permission);
    $user = User::factory()->for($role)->create();
    Setting::factory()->create([
        'group' => 'appointment',
        'key' => 'appointment.default_duration',
        'value' => '75',
        'type' => 'integer',
    ]);
    Cache::forget('clinic.settings');

    Livewire::actingAs($user)
        ->test(Form::class)
        ->assertSet('durationMinutes', 75)
        ->assertSee('wire:model.blur="durationMinutes"', escape: false)
        ->set('durationMinutes', null)
        ->assertSet('durationMinutes', null)
        ->set('durationMinutes', '3')
        ->assertSet('durationMinutes', '3')
        ->set('durationMinutes', '30')
        ->assertSet('durationMinutes', '30')
        ->assertHasNoErrors();
});

it('checks practitioner and room availability for the full selected duration', function () {
    $date = '2026-09-12';
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => 'appointments.manage']);
    $role->permissions()->attach($permission);
    $user = User::factory()->for($role)->create();
    $practitioner = Practitioner::factory()->create();
    $room = Room::factory()->create();
    $treatmentType = TreatmentType::factory()->create(['is_group' => false]);
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    Setting::factory()->create([
        'group' => 'appointment',
        'key' => 'appointment.default_duration',
        'value' => '90',
        'type' => 'integer',
    ]);
    Cache::forget('clinic.settings');
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => CarbonImmutable::parse($date)->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
        'effective_to' => null,
    ]);

    $component = Livewire::actingAs($user)
        ->test(Form::class)
        ->set('practitionerId', $practitioner->id)
        ->set('roomId', $room->id)
        ->set('treatmentTypeId', $treatmentType->id)
        ->set('date', $date)
        ->set('startTime', '16:00')
        ->set('durationMinutes', 90)
        ->set('kind', AppointmentKind::Individual->value)
        ->set('patientIds', [$patient->id])
        ->call('save')
        ->assertHasErrors('schedule')
        ->assertSee('راجع تفاصيل التعارض')
        ->assertSee('دوام المعالج في اليوم المحدد');

    expect($component->get('schedulingConflict')['reason'])->toBe('practitioner_unavailable')
        ->and($component->get('schedulingConflict')['requested_start'])->toBe('16:00')
        ->and($component->get('schedulingConflict')['requested_end'])->toBe('17:30');
    $this->assertDatabaseCount('appointments', 0);
});

it('saves the selected duration and calculates the matching end time', function () {
    $date = '2026-09-12';
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['name' => 'appointments.manage']);
    $role->permissions()->attach($permission);
    $user = User::factory()->for($role)->create();
    $practitioner = Practitioner::factory()->create();
    $room = Room::factory()->create();
    $treatmentType = TreatmentType::factory()->create(['is_group' => false]);
    $patient = Patient::factory()->create(['current_practitioner_id' => $practitioner->id]);
    Setting::factory()->create([
        'group' => 'appointment',
        'key' => 'appointment.default_duration',
        'value' => '75',
        'type' => 'integer',
    ]);
    Cache::forget('clinic.settings');
    PractitionerAvailability::factory()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => CarbonImmutable::parse($date)->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'effective_from' => null,
        'effective_to' => null,
    ]);

    Livewire::actingAs($user)
        ->test(Form::class)
        ->set('practitionerId', $practitioner->id)
        ->set('roomId', $room->id)
        ->set('treatmentTypeId', $treatmentType->id)
        ->set('date', $date)
        ->set('startTime', '10:00')
        ->set('durationMinutes', 30)
        ->set('kind', AppointmentKind::Individual->value)
        ->set('patientIds', [$patient->id])
        ->call('save')
        ->assertRedirect(route('appointments.index', ['highlight' => 1]));

    $this->assertDatabaseHas('appointments', [
        'duration_minutes' => 30,
        'starts_at' => '2026-09-12 10:00:00',
        'ends_at' => '2026-09-12 10:30:00',
    ]);
});
