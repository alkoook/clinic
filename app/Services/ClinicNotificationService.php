<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Jobs\SendTelegramMessage;
use App\Models\Practitioner;
use App\Models\User;
use App\Notifications\ClinicEventNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class ClinicNotificationService
{
    public function __construct(private readonly TelegramService $telegram) {}

    /**
     * Send an appointment or session notification to administrative users,
     * secretaries, and only the practitioner involved in the operation.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function operational(
        string $eventKey,
        ClinicNotificationType $type,
        string $title,
        string $body,
        ?string $url = null,
        array $metadata = [],
        ?Practitioner $practitioner = null,
    ): void {
        $recipients = $this->usersForRoles(['super-admin', 'admin', 'secretary']);

        if ($practitioner?->user_id !== null) {
            $recipients->push($practitioner->user()->first());
        }

        $this->sendDatabase($recipients, $type, $title, $body, $url, $metadata);
        $this->queueTelegram($eventKey.':admin', $type, $title, $body);

        if (filled($practitioner?->telegram_chat_id)) {
            $this->queueTelegram(
                $eventKey.':practitioner:'.$practitioner->getKey(),
                $type,
                $title,
                $body,
                $practitioner->telegram_chat_id,
            );
        }
    }

    /**
     * Send a financial notification to administrators and, when relevant,
     * to the practitioner whose own financial record changed.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function financial(
        string $eventKey,
        ClinicNotificationType $type,
        string $adminTitle,
        string $adminBody,
        ?string $url = null,
        array $metadata = [],
        ?Practitioner $practitioner = null,
        ?string $practitionerTitle = null,
        ?string $practitionerBody = null,
    ): void {
        $this->sendDatabase(
            $this->usersForRoles(['super-admin', 'admin']),
            $type,
            $adminTitle,
            $adminBody,
            $url,
            $metadata,
        );
        $this->queueTelegram($eventKey.':admin', $type, $adminTitle, $adminBody);

        $practitionerUser = $practitioner?->user()->where('is_active', true)->first();

        if ($practitionerUser !== null && $practitionerTitle !== null && $practitionerBody !== null) {
            $this->sendDatabase(
                new Collection([$practitionerUser]),
                $type,
                $practitionerTitle,
                $practitionerBody,
                $url,
                $metadata,
            );

            if (filled($practitioner?->telegram_chat_id)) {
                $this->queueTelegram(
                    $eventKey.':practitioner:'.$practitioner->getKey(),
                    $type,
                    $practitionerTitle,
                    $practitionerBody,
                    $practitioner->telegram_chat_id,
                );
            }
        }
    }

    /** @param array<string, mixed> $metadata */
    public function administrative(
        string $eventKey,
        ClinicNotificationType $type,
        string $title,
        string $body,
        ?string $url = null,
        array $metadata = [],
    ): void {
        $this->sendDatabase($this->usersForRoles(['super-admin', 'admin']), $type, $title, $body, $url, $metadata);
        $this->queueTelegram($eventKey.':admin', $type, $title, $body);
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $metadata
     */
    private function sendDatabase(
        Collection $recipients,
        ClinicNotificationType $type,
        string $title,
        string $body,
        ?string $url,
        array $metadata,
    ): void {
        $recipients = $recipients
            ->filter(static fn (mixed $user): bool => $user instanceof User && $user->is_active)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::sendNow(
                $recipients,
                new ClinicEventNotification($type, $title, $body, $url, $metadata),
            );
        } catch (Throwable $exception) {
            Log::warning('In-app notification persistence failed.', [
                'type' => $type->value,
                'exception' => $exception::class,
            ]);
        }
    }

    private function queueTelegram(
        string $eventKey,
        ClinicNotificationType $type,
        string $title,
        string $body,
        ?string $chatId = null,
    ): void {
        if (! $this->telegram->isEnabledFor($type->group())) {
            return;
        }

        DB::afterCommit(static function () use ($eventKey, $type, $title, $body, $chatId): void {
            try {
                SendTelegramMessage::dispatch(
                    $eventKey,
                    $type->group(),
                    $title."\n\n".$body,
                    $chatId,
                )->afterCommit();
            } catch (Throwable $exception) {
                Log::warning('Telegram notification could not be queued.', [
                    'event_key' => $eventKey,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    /**
     * @param  array<int, string>  $roles
     * @return Collection<int, User>
     */
    private function usersForRoles(array $roles): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', static fn ($query) => $query->whereIn('name', $roles))
            ->get();
    }
}
