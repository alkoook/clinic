<?php

use App\Livewire\Appointments\Upcoming;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('shows the nearest non-cancelled appointment for each practitioner', function () {
    $this->travelTo('2026-09-21 10:00:00');
    $admin = User::factory()->for(Role::factory()->create(['name' => 'super-admin']))->create();
    $firstPractitioner = Practitioner::factory()->create(['name' => 'د. محمد']);
    $secondPractitioner = Practitioner::factory()->create(['name' => 'د. نور']);

    $firstNearest = Appointment::factory()->for($firstPractitioner)->create(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3)]);
    $firstLater = Appointment::factory()->for($firstPractitioner)->create(['starts_at' => now()->addHours(4), 'ends_at' => now()->addHours(5)]);
    $secondNearest = Appointment::factory()->for($secondPractitioner)->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    $cancelled = Appointment::factory()->for($secondPractitioner)->cancelled()->create(['starts_at' => now()->addMinutes(30), 'ends_at' => now()->addMinutes(90)]);

    $firstPatient = Patient::factory()->create(['full_name' => 'مريض محمد']);
    $laterPatient = Patient::factory()->create(['full_name' => 'موعد محمد اللاحق']);
    $secondPatient = Patient::factory()->create(['full_name' => 'مريض نور']);
    $cancelledPatient = Patient::factory()->create(['full_name' => 'مريض ملغى']);
    AppointmentParticipant::factory()->create(['appointment_id' => $firstNearest->id, 'patient_id' => $firstPatient->id]);
    AppointmentParticipant::factory()->create(['appointment_id' => $firstLater->id, 'patient_id' => $laterPatient->id]);
    AppointmentParticipant::factory()->create(['appointment_id' => $secondNearest->id, 'patient_id' => $secondPatient->id]);
    AppointmentParticipant::factory()->create(['appointment_id' => $cancelled->id, 'patient_id' => $cancelledPatient->id]);

    Livewire::actingAs($admin)
        ->test(Upcoming::class)
        ->assertSeeInOrder(['د. نور', 'مريض نور', 'د. محمد', 'مريض محمد'])
        ->assertDontSee('موعد محمد اللاحق')
        ->assertDontSee('مريض ملغى');
});

it('limits the indicator to the practitioners own appointments', function () {
    $this->travelTo('2026-09-21 10:00:00');
    $role = Role::factory()->create(['name' => 'practitioner']);
    $user = User::factory()->for($role)->create();
    $ownPractitioner = Practitioner::factory()->for($user)->create(['name' => 'د. سامر']);
    $otherPractitioner = Practitioner::factory()->create(['name' => 'د. آخر']);
    $ownAppointment = Appointment::factory()->for($ownPractitioner)->create(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
    $otherAppointment = Appointment::factory()->for($otherPractitioner)->create(['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3)]);
    $ownPatient = Patient::factory()->create(['full_name' => 'مريض سامر']);
    $otherPatient = Patient::factory()->create(['full_name' => 'مريض آخر']);
    AppointmentParticipant::factory()->create(['appointment_id' => $ownAppointment->id, 'patient_id' => $ownPatient->id]);
    AppointmentParticipant::factory()->create(['appointment_id' => $otherAppointment->id, 'patient_id' => $otherPatient->id]);

    Livewire::actingAs($user)
        ->test(Upcoming::class)
        ->assertSee('د. سامر')
        ->assertSee('مريض سامر')
        ->assertDontSee('د. آخر')
        ->assertDontSee('مريض آخر');
});
