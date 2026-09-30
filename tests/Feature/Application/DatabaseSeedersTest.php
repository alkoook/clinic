<?php

use App\Models\Cashbox;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\TreatmentType;
use App\Models\User;
use Database\Seeders\ClinicReferenceSeeder;
use Database\Seeders\DevelopmentAdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

it('seeds the role permission matrix idempotently', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->assertDatabaseCount('roles', 3);
    $this->assertDatabaseCount('permissions', count(config('clinic.permissions')));

    $superAdmin = Role::query()->where('name', 'super-admin')->sole();
    $secretary = Role::query()->where('name', 'secretary')->sole();
    $practitioner = Role::query()->where('name', 'practitioner')->sole();

    expect($superAdmin->permissions()->count())->toBe(count(config('clinic.permissions')))
        ->and($secretary->permissions()->pluck('name')->all())
        ->toContain('appointments.manage', 'expenses.manage', 'rooms.view')
        ->not->toContain('financial.agreements.manage', 'cashboxes.manage')
        ->and($practitioner->permissions()->pluck('name')->all())
        ->toContain('patients.view_own', 'appointments.view_own', 'reports.view_own')
        ->not->toContain('patients.view', 'reports.view');
});

it('seeds clinic reference data idempotently', function () {
    $this->seed(ClinicReferenceSeeder::class);
    $this->seed(ClinicReferenceSeeder::class);

    expect(Setting::query()->count())->toBe(6)
        ->and(Specialty::query()->count())->toBe(3)
        ->and(TreatmentType::query()->count())->toBe(4)
        ->and(Room::query()->count())->toBe(3)
        ->and(Cashbox::query()->count())->toBe(2)
        ->and(ExpenseCategory::query()->count())->toBe(5);

    $this->assertDatabaseHas('rooms', ['code' => 'R-01', 'is_active' => true]);
    $this->assertDatabaseHas('cashboxes', ['type' => 'main', 'is_active' => true]);
    $this->assertDatabaseHas('cashboxes', ['type' => 'side', 'is_active' => true]);
    $this->assertDatabaseHas('settings', ['key' => 'financial.currency', 'value' => 'SYP']);
});

it('creates the configured development administrator outside production', function () {
    $this->seed(RolePermissionSeeder::class);
    config()->set('clinic.development_admin', [
        'name' => 'Clinic Owner',
        'username' => '  OWNER.ADMIN  ',
        'email' => 'owner@example.test',
        'password' => 'a-secure-development-password',
    ]);

    $this->seed(DevelopmentAdminSeeder::class);

    $administrator = User::query()->where('username', 'owner.admin')->sole();

    expect($administrator->name)->toBe('Clinic Owner')
        ->and($administrator->role->name)->toBe('super-admin')
        ->and($administrator->is_active)->toBeTrue()
        ->and(Hash::check('a-secure-development-password', $administrator->password))->toBeTrue();
});

it('creates the fallback development administrator once when credentials are not configured', function () {
    $this->seed(RolePermissionSeeder::class);
    config()->set('clinic.development_admin.username');
    config()->set('clinic.development_admin.password');

    $this->seed(DevelopmentAdminSeeder::class);
    $originalPasswordHash = User::query()->where('username', 'admin')->sole()->password;
    $this->seed(DevelopmentAdminSeeder::class);

    $this->assertDatabaseCount('users', 1);
    expect(User::query()->where('username', 'admin')->sole()->password)->toBe($originalPasswordHash);
});

it('never creates the development administrator in production', function () {
    $this->seed(RolePermissionSeeder::class);
    config()->set('clinic.development_admin', [
        'name' => 'Production Owner',
        'username' => 'production.owner',
        'email' => 'production@example.test',
        'password' => 'must-not-be-seeded',
    ]);
    $originalEnvironment = $this->app->environment();
    $this->app->detectEnvironment(static fn (): string => 'production');

    try {
        (new DevelopmentAdminSeeder)->run();
    } finally {
        $this->app->detectEnvironment(static fn (): string => $originalEnvironment);
    }

    $this->assertDatabaseCount('users', 0);
});
