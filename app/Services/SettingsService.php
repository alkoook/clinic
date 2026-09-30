<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SettingsService
{
    /**
     * @return array<string, array{value: string|null, type: string}>
     */
    public function all(): array
    {
        return Cache::remember('clinic.settings', now()->addHour(), static fn (): array => Setting::query()
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(static fn (Setting $setting): array => [
                $setting->key => ['value' => $setting->value, 'type' => $setting->type],
            ])->all());
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $setting = $this->all()[$key] ?? null;

        if ($setting === null || $setting['value'] === null) {
            return $default;
        }

        if ($setting['type'] !== 'secret') {
            return $setting['value'];
        }

        try {
            return Crypt::decryptString($setting['value']);
        } catch (Throwable) {
            return $default;
        }
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->string($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->string($key);

        return $value === null ? $default : (int) $value;
    }

    /**
     * @param  array<string, array{group: string, value: string|int|bool|null, type?: string}>  $values
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $attributes) {
                $type = $attributes['type'] ?? 'string';
                $value = $attributes['value'];

                if ($type === 'secret' && filled($value)) {
                    $value = Crypt::encryptString((string) $value);
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $attributes['group'],
                        'value' => $value === null ? null : (string) $value,
                        'type' => $type,
                    ],
                );
            }
        });

        Cache::forget('clinic.settings');
    }
}
