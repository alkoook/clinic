<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = collect(config('clinic.permissions'))
                ->map(function (array $attributes, string $name): Permission {
                    return Permission::query()->updateOrCreate(
                        ['name' => $name],
                        ['label' => $attributes['label'], 'group' => $attributes['group']],
                    );
                });

            $roles = [
                'super-admin' => [
                    'label' => 'المدير العام',
                    'permissions' => $permissions->keys()->all(),
                ],
                'secretary' => [
                    'label' => 'السكرتارية',
                    'permissions' => [
                        'dashboard.operational.view',
                        'patients.view',
                        'patients.create',
                        'patients.update',
                        'patient_attachments.view',
                        'patient_attachments.upload',
                        'appointments.manage',
                        'sessions.manage',
                        'payments.create',
                        'expenses.manage',
                        'rooms.view',
                    ],
                ],
                'practitioner' => [
                    'label' => 'طبيب / معالج',
                    'permissions' => [
                        'dashboard.operational.view',
                        'patients.view_own',
                        'patient_attachments.view',
                        'patient_attachments.preview',
                        'patient_attachments.download',
                        'appointments.view_own',
                        'practitioner.availability.manage_own',
                        'sessions.view_own',
                        'reports.view_own',
                    ],
                ],
            ];

            foreach ($roles as $name => $definition) {
                $role = Role::query()->updateOrCreate(
                    ['name' => $name],
                    ['label' => $definition['label']],
                );

                $role->permissions()->sync(
                    $permissions->only($definition['permissions'])->pluck('id')->all(),
                );
            }
        });
    }
}
