<?php

use App\Livewire\Settings\Index;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

function brandingAdministrator(): User
{
    $permission = Permission::query()->firstOrCreate(['name' => 'settings.manage'], ['label' => 'settings.manage', 'group' => 'system']);
    $role = Role::query()->create(['name' => 'branding-admin-'.str()->random(8), 'label' => 'مدير الهوية']);
    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
}

it('allows an authorized administrator to upload safe branding assets', function () {
    Storage::fake('public');
    $administrator = brandingAdministrator();

    Livewire::actingAs($administrator)->test(Index::class)
        ->set('logo', UploadedFile::fake()->image('clinic.webp', 300, 300))
        ->set('favicon', UploadedFile::fake()->image('favicon.png', 128, 128))
        ->call('saveBranding')
        ->assertHasNoErrors();

    $logoPath = app(SettingsService::class)->string('branding.logo_path');
    $faviconPath = app(SettingsService::class)->string('branding.favicon_path');
    expect($logoPath)->toStartWith('branding/')->and($faviconPath)->toStartWith('branding/');
    Storage::disk('public')->assertExists($logoPath);
    Storage::disk('public')->assertExists($faviconPath);
});

it('rejects unsafe branding uploads and denies non administrators', function () {
    Storage::fake('public');
    $administrator = brandingAdministrator();

    Livewire::actingAs($administrator)->test(Index::class)
        ->set('logo', UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'))
        ->call('saveBranding')
        ->assertHasErrors(['logo']);

    $this->actingAs(User::factory()->create())->get(route('settings.index'))->assertForbidden();
});

it('renders configured branding inside nested sidebar and print components', function () {
    app(SettingsService::class)->setMany([
        'clinic.name' => ['group' => 'clinic', 'value' => 'عيادة الاختبار العربية'],
        'branding.logo_path' => ['group' => 'branding', 'value' => 'branding/clinic.webp'],
    ]);
    $html = Blade::render('<x-ui.brand /><x-ui.print-brand-header />');
    expect(substr_count($html, 'عيادة الاختبار العربية'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('branding/clinic.webp');
});
