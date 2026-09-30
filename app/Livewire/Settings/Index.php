<?php

namespace App\Livewire\Settings;

use App\Exceptions\TelegramException;
use App\Services\ClinicWorkingHoursService;
use App\Services\SettingsService;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('الإعدادات')]
class Index extends Component
{
    use WithFileUploads;

    public string $tab = 'clinic';

    public string $clinicName = 'عيادة الحياة الطيبة';

    public string $clinicShortName = 'عيادة الحياة';

    public mixed $logo = null;

    public mixed $favicon = null;

    public ?string $logoPath = null;

    public ?string $faviconPath = null;

    public string $clinicPhone = '';

    public string $clinicAddress = '';

    public int $defaultAppointmentDuration = 60;

    public string $defaultTheme = 'light';

    /** @var array<int, array{enabled: bool, start: string, end: string}> */
    public array $clinicWorkingHours = [];

    public bool $telegramEnabled = false;

    public string $telegramBotToken = '';

    public string $telegramDefaultChatId = '';

    public bool $telegramTokenConfigured = false;

    /** @var array<string, bool> */
    public array $telegramNotifications = [
        'appointments' => true,
        'sessions' => true,
        'payments' => true,
        'refunds' => true,
        'expenses' => true,
        'settlements' => true,
        'cashboxes' => true,
        'weekly_closing' => true,
        'patients' => true,
    ];

    public function mount(): void
    {
        Gate::authorize('settings.manage');
        $settings = app(SettingsService::class);
        $this->clinicName = $settings->string('clinic.name', config('app.name')) ?? config('app.name');
        $this->clinicShortName = $settings->string('clinic.short_name', config('clinic.short_name')) ?? config('clinic.short_name');
        $this->logoPath = $settings->string('branding.logo_path');
        $this->faviconPath = $settings->string('branding.favicon_path');
        $this->clinicPhone = $settings->string('clinic.phone', '') ?? '';
        $this->clinicAddress = $settings->string('clinic.address', '') ?? '';
        $this->defaultAppointmentDuration = $settings->integer('appointment.default_duration', 60);
        $this->defaultTheme = $settings->string('appearance.default_theme', 'light') ?? 'light';
        $this->clinicWorkingHours = app(ClinicWorkingHoursService::class)->schedule();
        $this->telegramEnabled = $settings->boolean('telegram.enabled');
        $this->telegramDefaultChatId = $settings->string('telegram.default_chat_id', '') ?? '';
        $this->telegramTokenConfigured = filled($settings->string('telegram.bot_token'));

        foreach (array_keys($this->telegramNotifications) as $group) {
            $this->telegramNotifications[$group] = $settings->boolean("telegram.notifications.{$group}", true);
        }
    }

    public function save(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');
        $validated = $this->validate([
            'clinicName' => ['required', 'string', 'max:255'],
            'clinicShortName' => ['required', 'string', 'max:80'],
            'clinicPhone' => ['nullable', 'string', 'max:64'],
            'clinicAddress' => ['nullable', 'string', 'max:500'],
            'defaultAppointmentDuration' => ['required', 'integer', 'min:5', 'max:720'],
            'defaultTheme' => ['required', Rule::in(['light', 'dark'])],
            'clinicWorkingHours' => ['required', 'array', 'size:7'],
            'clinicWorkingHours.*.enabled' => ['required', 'boolean'],
            'clinicWorkingHours.*.start' => ['required', 'date_format:H:i'],
            'clinicWorkingHours.*.end' => ['required', 'date_format:H:i'],
        ]);

        $values = [
            'clinic.name' => ['group' => 'clinic', 'value' => trim($validated['clinicName']), 'type' => 'string'],
            'clinic.short_name' => ['group' => 'clinic', 'value' => trim($validated['clinicShortName']), 'type' => 'string'],
            'clinic.phone' => ['group' => 'clinic', 'value' => trim($validated['clinicPhone']), 'type' => 'string'],
            'clinic.address' => ['group' => 'clinic', 'value' => trim($validated['clinicAddress']), 'type' => 'string'],
            'appointment.default_duration' => ['group' => 'appointment', 'value' => (string) $validated['defaultAppointmentDuration'], 'type' => 'integer'],
            'appearance.default_theme' => ['group' => 'appearance', 'value' => $validated['defaultTheme'], 'type' => 'string'],
            'clinic.working_hours' => ['group' => 'clinic', 'value' => json_encode($validated['clinicWorkingHours'], JSON_UNESCAPED_UNICODE), 'type' => 'json'],
        ];

        foreach ($validated['clinicWorkingHours'] as $period) {
            if ($period['enabled'] && $period['start'] >= $period['end']) {
                $this->addError('clinicWorkingHours', 'يجب أن يبدأ دوام العيادة قبل وقت انتهائه.');

                return;
            }
        }

        $settings->setMany($values);

        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ الإعدادات.');
    }

    public function saveBranding(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');

        $this->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=96,min_height=96,max_width=2400,max_height=2400'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,webp', 'max:512'],
        ]);

        $values = [];

        if ($this->logo !== null) {
            $path = $this->logo->storePublicly('branding', 'public');
            $this->deleteBrandAsset($this->logoPath);
            $this->logoPath = $path;
            $values['branding.logo_path'] = ['group' => 'branding', 'value' => $path, 'type' => 'string'];
        }

        if ($this->favicon !== null) {
            $path = $this->favicon->storePublicly('branding', 'public');
            $this->deleteBrandAsset($this->faviconPath);
            $this->faviconPath = $path;
            $values['branding.favicon_path'] = ['group' => 'branding', 'value' => $path, 'type' => 'string'];
        }

        if ($values !== []) {
            $settings->setMany($values);
        }

        $this->reset('logo', 'favicon');
        $this->dispatch('app-toast', type: 'success', message: 'تم تحديث الهوية البصرية للعيادة.');
    }

    public function resetLogo(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');
        $this->deleteBrandAsset($this->logoPath);
        $this->logoPath = null;
        $settings->setMany(['branding.logo_path' => ['group' => 'branding', 'value' => null, 'type' => 'string']]);
        $this->dispatch('app-toast', type: 'success', message: 'تمت استعادة شعار العيادة الافتراضي.');
    }

    public function resetFavicon(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');
        $this->deleteBrandAsset($this->faviconPath);
        $this->faviconPath = null;
        $settings->setMany(['branding.favicon_path' => ['group' => 'branding', 'value' => null, 'type' => 'string']]);
        $this->dispatch('app-toast', type: 'success', message: 'تمت استعادة أيقونة التطبيق الافتراضية.');
    }

    private function deleteBrandAsset(?string $path): void
    {
        if (filled($path) && str_starts_with($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function saveTelegram(SettingsService $settings): void
    {
        Gate::authorize('settings.manage');

        if (! $this->persistTelegramSettings($settings)) {
            return;
        }

        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ إعدادات تيلغرام بأمان.');
    }

    public function testTelegram(SettingsService $settings, TelegramService $telegram): void
    {
        Gate::authorize('settings.manage');

        if (! $this->telegramEnabled) {
            $this->addError('telegramEnabled', 'فعّل تيلغرام أولًا لإرسال رسالة تجريبية.');

            return;
        }

        if (! $this->persistTelegramSettings($settings)) {
            return;
        }

        try {
            $telegram->send('✅ رسالة اختبار من نظام عيادة الحياة الطيبة. الاتصال يعمل بنجاح.');
            $this->dispatch('app-toast', type: 'success', message: 'تم إرسال رسالة الاختبار إلى تيلغرام بنجاح.');
        } catch (TelegramException $exception) {
            $this->addError('telegramTest', $exception->getMessage());
        }
    }

    private function persistTelegramSettings(SettingsService $settings): bool
    {
        $this->validate([
            'telegramEnabled' => ['boolean'],
            'telegramBotToken' => ['nullable', 'string', 'min:20', 'max:255'],
            'telegramDefaultChatId' => ['nullable', 'string', 'max:128'],
            'telegramNotifications' => ['required', 'array'],
            'telegramNotifications.*' => ['boolean'],
        ]);

        if ($this->telegramEnabled && blank($this->telegramBotToken) && ! $this->telegramTokenConfigured) {
            $this->addError('telegramBotToken', 'رمز البوت مطلوب عند تفعيل تيلغرام.');

            return false;
        }

        if ($this->telegramEnabled && blank($this->telegramDefaultChatId)) {
            $this->addError('telegramDefaultChatId', 'معرّف المحادثة الافتراضية مطلوب عند تفعيل تيلغرام.');

            return false;
        }

        $values = [
            'telegram.enabled' => ['group' => 'telegram', 'value' => $this->telegramEnabled, 'type' => 'boolean'],
            'telegram.default_chat_id' => ['group' => 'telegram', 'value' => trim($this->telegramDefaultChatId), 'type' => 'string'],
        ];

        if (filled($this->telegramBotToken)) {
            $values['telegram.bot_token'] = [
                'group' => 'telegram',
                'value' => trim($this->telegramBotToken),
                'type' => 'secret',
            ];
        }

        foreach ($this->telegramNotifications as $group => $enabled) {
            $values["telegram.notifications.{$group}"] = [
                'group' => 'telegram',
                'value' => $enabled,
                'type' => 'boolean',
            ];
        }

        $settings->setMany($values);
        $this->telegramTokenConfigured = filled($settings->string('telegram.bot_token'));
        $this->telegramBotToken = '';

        return true;
    }

    public function render()
    {
        return view('livewire.settings.index');
    }
}
