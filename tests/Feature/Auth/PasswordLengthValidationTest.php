<?php

use App\Livewire\Users\Index as UsersIndex;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

it('accepts six character passwords when an administrator creates a user', function () {
    $this->seed(RolePermissionSeeder::class);
    $administrator = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();
    $role = Role::query()->where('name', 'secretary')->sole();

    Livewire::actingAs($administrator)
        ->test(UsersIndex::class)
        ->set('name', 'مستخدم جديد')
        ->set('username', 'new-user')
        ->set('roleId', $role->id)
        ->set('password', '123456')
        ->set('passwordConfirmation', '123456')
        ->call('save')
        ->assertHasNoErrors();
});

it('rejects passwords shorter than six characters when creating a user', function () {
    $this->seed(RolePermissionSeeder::class);
    $administrator = User::factory()->for(Role::query()->where('name', 'super-admin')->sole())->create();
    $role = Role::query()->where('name', 'secretary')->sole();

    Livewire::actingAs($administrator)
        ->test(UsersIndex::class)
        ->set('name', 'مستخدم جديد')
        ->set('username', 'short-user')
        ->set('roleId', $role->id)
        ->set('password', '12345')
        ->set('passwordConfirmation', '12345')
        ->call('save')
        ->assertHasErrors(['password' => 'min']);
});
