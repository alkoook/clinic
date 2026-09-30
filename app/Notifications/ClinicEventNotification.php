<?php

namespace App\Notifications;

use App\Enums\ClinicNotificationType;
use Illuminate\Notifications\Notification;

final class ClinicEventNotification extends Notification
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly ClinicNotificationType $eventType,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->eventType->value;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->eventType->value,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'metadata' => $this->metadata,
        ];
    }
}
