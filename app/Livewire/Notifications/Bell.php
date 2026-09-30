<?php

namespace App\Livewire\Notifications;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Bell extends Component
{
    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /** @return Collection<int, DatabaseNotification> */
    #[Computed]
    public function recentNotifications(): Collection
    {
        return auth()->user()->notifications()->latest()->limit(8)->get();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->find($notificationId);
        abort_unless($notification, 403);
        $notification->markAsRead();
        unset($this->unreadCount, $this->recentNotifications);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->unreadCount, $this->recentNotifications);
    }

    public function render()
    {
        return view('livewire.notifications.bell');
    }
}
