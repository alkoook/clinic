<?php

namespace App\Livewire\Notifications;

use App\Enums\ClinicNotificationType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('الإشعارات')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'الحالة')]
    public string $status = 'all';

    #[Url(as: 'النوع')]
    public string $type = '';

    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        return auth()->user()->notifications()
            ->when($this->status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($this->status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->latest()
            ->paginate(15);
    }

    /** @return array<int, ClinicNotificationType> */
    #[Computed]
    public function types(): array
    {
        return ClinicNotificationType::cases();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->find($notificationId);
        abort_unless($notification, 403);
        $notification->markAsRead();
        unset($this->notifications);
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        unset($this->notifications);
    }

    public function delete(string $notificationId): void
    {
        $notification = auth()->user()->notifications()->find($notificationId);
        abort_unless($notification, 403);
        $notification->delete();
        unset($this->notifications);
    }

    public function render()
    {
        return view('livewire.notifications.index');
    }
}
