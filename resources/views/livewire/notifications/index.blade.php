<div class="space-y-6">
    <header class="page-header"><div><p class="page-kicker">مركز المتابعة</p><h1>الإشعارات</h1><p>كل التنبيهات التشغيلية والمالية المسموح لك برؤيتها.</p></div><button class="btn btn-secondary" type="button" wire:click="markAllAsRead">تعليم الكل كمقروء</button></header>

    <section class="app-card grid gap-3 p-4 sm:grid-cols-2">
        <label><span class="form-label">الحالة</span><select class="form-select" wire:model.live="status"><option value="all">الكل</option><option value="unread">غير المقروء</option><option value="read">المقروء</option></select></label>
        <label><span class="form-label">النوع</span><select class="form-select" wire:model.live="type"><option value="">كل الأنواع</option>@foreach ($this->types as $notificationType)<option value="{{ $notificationType->value }}">{{ $notificationType->label() }}</option>@endforeach</select></label>
    </section>

    <section class="space-y-3">
        @forelse ($this->notifications as $notification)
            @php($notificationType = App\Enums\ClinicNotificationType::tryFrom($notification->type))
            <article class="app-card p-4 sm:p-5 {{ $notification->read_at ? '' : 'border-r-4 border-r-[var(--brand)]' }}" wire:key="notification-{{ $notification->id }}">
                <div class="flex items-start gap-3 sm:gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-[var(--brand-soft)] text-[var(--brand-strong)]"><x-ui.icon :name="$notificationType?->icon() ?? 'bell'" class="size-5" /></span>
                    <div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div><h2 class="font-black text-[var(--ink)]">{{ $notification->data['title'] ?? $notificationType?->label() ?? 'إشعار' }}</h2><p class="mt-1 text-sm text-[var(--ink-soft)]">{{ $notification->data['body'] ?? '' }}</p></div><time class="shrink-0 text-xs text-[var(--muted)]" title="{{ $notification->created_at->format('Y/m/d H:i') }}">{{ $notification->created_at->diffForHumans() }}</time></div><div class="mt-3 flex flex-wrap gap-2">@if (filled($notification->data['url'] ?? null))<a class="btn btn-secondary" href="{{ $notification->data['url'] }}" wire:click="markAsRead('{{ $notification->id }}')" wire:navigate>فتح التفاصيل</a>@endif @unless ($notification->read_at)<button class="btn btn-secondary" type="button" wire:click="markAsRead('{{ $notification->id }}')">تعليم كمقروء</button>@endunless <button class="btn btn-secondary text-red-600" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف الإشعار؟', description: 'سيُحذف هذا الإشعار من قائمتك فقط.', confirmLabel: 'حذف الإشعار', action: () => $wire.delete(@js($notification->id)) })">حذف</button></div></div>
                </div>
            </article>
        @empty
            <div class="empty-state">لا توجد إشعارات مطابقة.</div>
        @endforelse
    </section>

    {{ $this->notifications->links() }}
</div>

