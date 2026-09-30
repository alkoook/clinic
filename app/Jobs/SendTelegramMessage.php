<?php

namespace App\Jobs;

use App\Services\TelegramService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendTelegramMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $eventKey,
        public readonly string $group,
        public readonly string $message,
        public readonly ?string $chatId = null,
    ) {}

    public function uniqueId(): string
    {
        return hash('sha256', $this->eventKey.'|'.($this->chatId ?? 'default'));
    }

    /** @return array<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Execute the job.
     */
    public function handle(TelegramService $telegram): void
    {
        if (! $telegram->isEnabledFor($this->group)) {
            return;
        }

        $telegram->send($this->message, $this->chatId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Telegram notification delivery failed.', [
            'event_key' => $this->eventKey,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
