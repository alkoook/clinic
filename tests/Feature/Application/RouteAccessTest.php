<?php

use App\Livewire\Appointments\Index as AppointmentIndex;
use App\Livewire\Dashboard;
use App\Livewire\Finance\Agreements;
use App\Livewire\MasterData\Rooms as RoomsComponent;
use App\Livewire\MasterData\Specialties as SpecialtiesComponent;
use App\Livewire\MasterData\TreatmentTypes as TreatmentTypesComponent;
use App\Livewire\Patients\Index as PatientIndex;
use App\Livewire\Reports\Index as ReportIndex;
use App\Livewire\Users\Index as UserIndex;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Role;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\TreatmentType;
use App\Models\User;
use Database\Seeders\ClinicReferenceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('redirects an unauthenticated visitor from protected clinic pages', function () {
    $this->get(route('dashboard'))->assertRedirectToRoute('login');
    $this->get(route('patients.index'))->assertRedirectToRoute('login');
    $this->get(route('reports.index'))->assertRedirectToRoute('login');
});

it('renders administrator operational and finance pages', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $administrator = User::factory()
        ->for(Role::query()->where('name', 'super-admin')->sole())
        ->create();

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class);
    $this->actingAs($administrator)
        ->get(route('finance.agreements'))
        ->assertOk()
        ->assertSeeLivewire(Agreements::class);
});

it('renders secretary operational pages but keeps rooms read only and forbids administrative finance', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $secretary = User::factory()
        ->for(Role::query()->where('name', 'secretary')->sole())
        ->create();

    $this->actingAs($secretary)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class);
    $this->actingAs($secretary)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertSeeLivewire(PatientIndex::class);
    $this->actingAs($secretary)
        ->get(route('master-data.rooms'))
        ->assertOk()
        ->assertSeeLivewire(RoomsComponent::class);

    Livewire::actingAs($secretary)
        ->test(RoomsComponent::class)
        ->set('name', 'Unauthorized room')
        ->set('code', 'NO-01')
        ->call('save')
        ->assertForbidden();

    $this->assertDatabaseMissing('rooms', ['code' => 'NO-01']);
    $this->actingAs($secretary)->get(route('finance.agreements'))->assertForbidden();
    $this->actingAs($secretary)->get(route('finance.cashboxes'))->assertForbidden();
});

it('renders only practitioner-scoped operational and report pages', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $practitionerUser = User::factory()
        ->for(Role::query()->where('name', 'practitioner')->sole())
        ->create();
    Practitioner::factory()->for($practitionerUser)->create();

    $this->actingAs($practitionerUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class);
    $this->actingAs($practitionerUser)
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSeeLivewire(AppointmentIndex::class);
    $this->actingAs($practitionerUser)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertSeeLivewire(ReportIndex::class);
    $this->actingAs($practitionerUser)->get(route('patients.create'))->assertForbidden();
    $this->actingAs($practitionerUser)->get(route('finance.expenses'))->assertForbidden();
});

it('lets administrators soft delete reference data while preserving history', function () {
    $this->seed([RolePermissionSeeder::class, ClinicReferenceSeeder::class]);
    $administrator = User::factory()
        ->for(Role::query()->where('name', 'super-admin')->sole())
        ->create();
    $specialty = Specialty::query()->firstOrFail();
    $treatmentType = TreatmentType::query()->firstOrFail();
    $room = Room::query()->firstOrFail();
    $treatmentType->update(['specialty_id' => $specialty->id]);
    $appointment = Appointment::factory()
        ->for($room)
        ->for($treatmentType)
        ->create();

    Livewire::actingAs($administrator)
        ->test(SpecialtiesComponent::class)
        ->call('delete', $specialty->id)
        ->assertHasNoErrors();
    Livewire::actingAs($administrator)
        ->test(TreatmentTypesComponent::class)
        ->call('delete', $treatmentType->id)
        ->assertHasNoErrors();
    Livewire::actingAs($administrator)
        ->test(RoomsComponent::class)
        ->call('delete', $room->id)
        ->assertHasNoErrors();

    $this->assertSoftDeleted($specialty);
    $this->assertSoftDeleted($treatmentType);
    $this->assertSoftDeleted($room);
    expect($appointment->fresh()->room)->not->toBeNull()
        ->and($appointment->fresh()->treatmentType)->not->toBeNull()
        ->and(TreatmentType::query()->withTrashed()->with('specialty')->findOrFail($treatmentType->id)->specialty)->not->toBeNull();
});

it('does not allow removing the final active super administrator', function () {
    $this->seed(RolePermissionSeeder::class);
    $administratorRole = Role::query()->where('name', 'super-admin')->sole();
    $secretaryRole = Role::query()->where('name', 'secretary')->sole();
    $administrator = User::factory()->for($administratorRole)->create(['is_active' => true]);

    expect($administrator->role->name)->toBe('super-admin')
        ->and(User::query()->where('is_active', true)->whereHas('role', fn ($query) => $query->where('name', 'super-admin'))->count())->toBe(1);

    Livewire::actingAs($administrator)
        ->test(UserIndex::class)
        ->call('edit', $administrator->id)
        ->set('isActive', false)
        ->assertSet('isActive', false)
        ->call('save');

    expect($administrator->refresh()->is_active)->toBeTrue()
        ->and($administrator->role_id)->toBe($administratorRole->id);

    Livewire::actingAs($administrator)
        ->test(UserIndex::class)
        ->call('edit', $administrator->id)
        ->set('roleId', $secretaryRole->id)
        ->call('save');

    expect($administrator->refresh()->role_id)->toBe($administratorRole->id);
});
