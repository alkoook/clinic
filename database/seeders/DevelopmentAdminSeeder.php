<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevelopmentAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $username = config('clinic.development_admin.username') ?: 'admin';
        $configuredPassword = config('clinic.development_admin.password');
        $existingUser = User::query()->where('username', mb_strtolower(trim((string) $username)))->first();

        if ($existingUser !== null && blank($configuredPassword)) {
            return;
        }

        $password = filled($configuredPassword) ? (string) $configuredPassword : Str::random(32);

        $role = Role::query()->where('name', 'super-admin')->firstOrFail();

        $user = User::query()->updateOrCreate(
            ['username' => mb_strtolower(trim((string) $username))],
            [
                'role_id' => $role->id,
                'name' => config('clinic.development_admin.name', 'مدير النظام'),
                'email' => config('clinic.development_admin.email'),
                'password' => $password,
                'is_active' => true,
            ],
        );

        if ($user->wasRecentlyCreated && blank($configuredPassword)) {
            $this->command?->warn("بيانات مدير التطوير (تظهر مرة واحدة): {$user->username} / {$password}");
        }
    }
}
