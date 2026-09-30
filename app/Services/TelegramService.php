<?php

namespace App\Services;

use App\Exceptions\TelegramException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TelegramService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function isEnabledFor(string $group): bool
    {
        return $this->settings->boolean('telegram.enabled')
            && $this->settings->boolean("telegram.notifications.{$group}", true);
    }

    public function send(string $message, ?string $chatId = null): void
    {
        $token = $this->settings->string('telegram.bot_token');
        $destination = filled($chatId) ? trim((string) $chatId) : $this->settings->string('telegram.default_chat_id');

        if (blank($token) || blank($destination)) {
            throw TelegramException::missingConfiguration();
        }

        try {
            $response = Http::asJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $destination,
                    'text' => $message,
                    'disable_web_page_preview' => true,
                ]);

            if (! $response->successful() || $response->json('ok') !== true) {
                throw TelegramException::deliveryFailed();
            }
        } catch (TelegramException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw TelegramException::deliveryFailed();
        }
    }
}
