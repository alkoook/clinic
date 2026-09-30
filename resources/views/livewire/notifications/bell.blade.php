<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" wire:poll.60s.visible>
    <button class="icon-btn relative" type="button" aria-label="الإشعارات" x-on:click="open = ! open" x-bind:aria-expanded="open">
        <x-ui.icon name="bell" class="size-5" />
        @if ($this->unreadCount > 0)
            <span class="absolute -left-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-black leading-none text-white ring-2 ring-[var(--topbar)]">{{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}</span>
        @endif
    </button>

    <div
        class="fixed left-3 right-3 top-19 z-50 max-h-[min(34rem,calc(100vh-6rem))] overflow-hidden rounded-3xl border border-[var(--line)] bg-[var(--surface)] shadow-2xl sm:absolute sm:left-0 sm:right-auto sm:top-12 sm:w-[25rem]"
        x-cloak
        x-show="open"
        x-transition.opacity
        x-on:click.outside="open = false"
    >
        <div class="flex items-center justify-between gap-3 border-b border-[var(--line)] px-4 py-3">
            <div><p class="font-black text-[var(--ink)]">الإشعارات</p><p class="text-xs text-[var(--muted)]">{{ $this->unreadCount }} غير مقروء</p></div>
            @if ($this->unreadCount > 0)<button class="text-xs font-bold text-[var(--brand-strong)]" type="button" wire:click="markAllAsRead">تعليم الكل كمقروء</button>@endif
        </div>
        <div class="max-h-[27rem] overflow-y-auto">
            @forelse ($this->recentNotifications as $notification)
                @php($notificationType = App\Enums\ClinicNotificationType::tryFrom($notification->type))
                <article class="border-b border-[var(--line)] p-4 last:border-0 {{ $notification->read_at ? '' : 'bg-[var(--brand-soft)]/45' }}" wire:key="top-notification-{{ $notification->id }}">
                    <div class="flex gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-[var(--surface-soft)] text-[var(--brand-strong)]"><x-ui.icon :name="$notificationType?->icon() ?? 'bell'" class="size-4" /></span>
                        <div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><p class="font-black text-[var(--ink)]">{{ $notification->data['title'] ?? $notificationType?->label() ?? 'إشعار' }}</p>@unless ($notification->read_at)<span class="mt-1 size-2 shrink-0 rounded-full bg-[var(--brand)]"></span>@endunless</div><p class="mt-1 line-clamp-2 text-sm text-[var(--ink-soft)]">{{ $notification->data['body'] ?? '' }}</p><div class="mt-2 flex items-center justify-between gap-2"><time class="text-xs text-[var(--muted)]">{{ $notification->created_at->diffForHumans() }}</time>@unless ($notification->read_at)<button class="text-xs font-bold text-[var(--brand-strong)]" type="button" wire:click="markAsRead('{{ $notification->id }}')">مقروء</button>@endunless</div>@if (filled($notification->data['url'] ?? null))<a class="mt-2 inline-block text-xs font-black text-[var(--brand-strong)]" href="{{ $notification->data['url'] }}" wire:click="markAsRead('{{ $notification->id }}')" wire:navigate>فتح التفاصيل</a>@endif</div>
                    </div>
                </article>
            @empty
                <div class="empty-state m-4">لا توجد إشعارات بعد.</div>
            @endforelse
        </div>
        <a class="block border-t border-[var(--line)] px-4 py-3 text-center text-sm font-black text-[var(--brand-strong)]" href="{{ route('notifications.index') }}" wire:navigate>عرض جميع الإشعارات</a>
    </div>
</div>
