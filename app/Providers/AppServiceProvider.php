<?php

namespace App\Providers;

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\User;
use App\Policies\PatientAttachmentPolicy;
use App\Policies\PatientPolicy;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config()->set('livewire.temporary_file_upload.rules', ['required', 'file', 'max:25600']);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        App::setLocale((string) config('clinic.locale', 'ar'));

        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(PatientAttachment::class, PatientAttachmentPolicy::class);

        Gate::before(static fn (User $user): ?bool => $user->is_active ? null : false);

        foreach (array_keys(config('clinic.permissions', [])) as $ability) {
            Gate::define($ability, static fn (User $user): bool => $user->hasPermission($ability));
        }

        View::composer(['components.layouts.app', 'components.layouts.guest', 'components.ui.brand', 'components.ui.print-brand-header'], function (ViewContract $view): void {
            $user = Auth::user();
            $settings = App::make(SettingsService::class);

            $view->with([
                'navigationGroups' => $user ? $this->navigationFor($user) : [],
                'clinicName' => $settings->string('clinic.name', (string) config('clinic.name')),
                'clinicShortName' => $settings->string('clinic.short_name', (string) config('clinic.short_name')),
                'clinicLogoUrl' => filled($logoPath = $settings->string('branding.logo_path')) ? Storage::disk('public')->url($logoPath) : null,
                'clinicFaviconUrl' => filled($faviconPath = $settings->string('branding.favicon_path')) ? Storage::disk('public')->url($faviconPath) : null,
                'defaultTheme' => $settings->string('appearance.default_theme', 'light'),
            ]);
        });
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{label: string, route: string, icon: string, ability: array<int, string>, parameters?: array<string, int>}>}>
     */
    private function navigationFor(User $user): array
    {
        $user->loadMissing(['role.permissions', 'practitioner']);

        return collect(config('clinic.navigation', []))
            ->map(function (array $group) use ($user): array {
                $items = collect($group['items'])
                    ->filter(function (array $item) use ($user): bool {
                        if (! Route::has($item['route'])) {
                            return false;
                        }

                        if ($item['route'] === 'availability.mine' && (! $user->practitioner || $user->can('practitioners.manage') || $user->can('appointments.manage'))) {
                            return false;
                        }

                        $roles = Arr::wrap($item['roles'] ?? []);

                        if ($roles !== [] && ! in_array($user->role?->name, $roles, true)) {
                            return false;
                        }

                        $abilities = Arr::wrap($item['ability'] ?? []);

                        return $abilities === [] || Gate::forUser($user)->any($abilities);
                    })
                    ->map(function (array $item) use ($user): array {
                        if ($item['route'] === 'availability.mine' && $user->practitioner) {
                            $item['route'] = 'availability.manage';
                            $item['parameters'] = ['practitioner' => $user->practitioner->getKey()];
                            $item['active'] = 'availability.manage';
                        }

                        return $item;
                    })
                    ->values()
                    ->all();

                return ['label' => $group['label'], 'items' => $items];
            })
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();
    }
}
