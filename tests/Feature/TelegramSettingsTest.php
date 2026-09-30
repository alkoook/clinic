<?php

use App\Enums\ClinicNotificationType;
use App\Exceptions\TelegramException;
use App\Jobs\SendTelegramMessage;
use App\Livewire\Settings\Index;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClinicNotificationService;
use App\Services\SettingsService;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

function telegramSettingsUser(bool $authorized): User
{
    $role = Role::factory()->create([
        'name' => $authorized ? 'super-admin' : 'secretary',
        'label' => $authorized ? 'المدير العام' : 'السكرتارية',
    ]);

    if ($authorized) {
        $role->permissions()->attach(Permission::factory()->create([
            'name' => 'settings.manage',
            'label' => 'settings.manage',
        ]));
    }

    return User::factory()->for($role)->create();
}

/** @param array<string, bool|string> $overrides */
function configureTelegram(array $overrides = []): void
{
    $values = array_merge([
        'enabled' => true,
        'token' => '123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdef',
        'chat_id' => '-1001234567890',
    ], $overrides);

    $settings = app(SettingsService::class);
    $settings->setMany([
        'telegram.enabled' => ['group' => 'telegram', 'value' => $values['enabled'], 'type' => 'boolean'],
        'telegram.bot_token' => ['group' => 'telegram', 'value' => $values['token'], 'type' => 'secret'],
        'telegram.default_chat_id' => ['group' => 'telegram', 'value' => $values['chat_id'], 'type' => 'string'],
        'telegram.notifications.appointments' => ['group' => 'telegram', 'value' => true, 'type' => 'boolean'],
    ]);
}

it('does not issue an HTTP request when Telegram is disabled', function () {
    configureTelegram(['enabled' => false]);

    (new SendTelegramMessage('appointment:1', 'appointments', 'اختبار'))->handle(app(TelegramService::class));

    Http::assertNothingSent();
});

it('keeps in-app delivery successful when Telegram configuration is missing', function () {
    $admin = telegramSettingsUser(true);
    app(SettingsService::class)->setMany([
        'telegram.enabled' => ['group' => 'telegram', 'value' => true, 'type' => 'boolean'],
        'telegram.notifications.appointments' => ['group' => 'telegram', 'value' => true, 'type' => 'boolean'],
    ]);

    app(ClinicNotificationService::class)->administrative(
        'appointment:1:created',
        ClinicNotificationType::AppointmentCreated,
        'موعد جديد',
        'تفاصيل الموعد',
    );

    expect($admin->notifications()->count())->toBe(1);
    Http::assertNothingSent();
});

it('isolates Telegram API failure from the successful core operation', function () {
    $admin = telegramSettingsUser(true);
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);

    app(ClinicNotificationService::class)->administrative(
        'appointment:2:created',
        ClinicNotificationType::AppointmentCreated,
        'موعد جديد',
        'تفاصيل الموعد',
    );

    expect($admin->notifications()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('never exposes the stored Telegram bot token in settings HTML or safe exceptions', function () {
    $admin = telegramSettingsUser(true);
    $token = '987654321:THIS_IS_A_SECRET_TELEGRAM_TOKEN';
    configureTelegram(['token' => $token]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

    Livewire::actingAs($admin)->test(Index::class)->assertDontSee($token);
    $storedValue = Setting::query()->where('key', 'telegram.bot_token')->value('value');

    expect($storedValue)->not->toContain($token)
        ->and(fn () => app(TelegramService::class)->send('اختبار'))
        ->toThrow(TelegramException::class, 'تعذر إرسال رسالة تيلغرام');
});

it('allows an administrator to update encrypted Telegram settings', function () {
    $admin = telegramSettingsUser(true);
    $token = '123456789:NEW_SECURE_TELEGRAM_TOKEN_VALUE';

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('telegramEnabled', true)
        ->set('telegramBotToken', $token)
        ->set('telegramDefaultChatId', '-100987654321')
        ->call('saveTelegram')
        ->assertHasNoErrors();

    expect(app(SettingsService::class)->string('telegram.bot_token'))->toBe($token)
        ->and(Setting::query()->where('key', 'telegram.bot_token')->value('value'))->not->toContain($token);
});

it('denies Telegram settings access to a non-admin user', function () {
    $secretary = telegramSettingsUser(false);

    Livewire::actingAs($secretary)->test(Index::class)->assertForbidden();
});

it('sends a real-format test Telegram request through the mocked HTTP client', function () {
    $admin = telegramSettingsUser(true);
    $token = '123456789:TEST_TELEGRAM_TOKEN_VALUE_ABC';
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('telegramEnabled', true)
        ->set('telegramBotToken', $token)
        ->set('telegramDefaultChatId', '-1001234567890')
        ->call('testTelegram')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage')
        && $request['chat_id'] === '-1001234567890'
        && str_contains($request['text'], 'رسالة اختبار'));
});

it('does not send an external notification for a rolled-back database transaction', function () {
    $admin = telegramSettingsUser(true);
    configureTelegram();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    expect(fn () => DB::transaction(function (): void {
        app(ClinicNotificationService::class)->administrative(
            'appointment:3:created',
            ClinicNotificationType::AppointmentCreated,
            'موعد لن يكتمل',
            'تفاصيل',
        );

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect($admin->notifications()->count())->toBe(0);
    Http::assertNothingSent();
});
