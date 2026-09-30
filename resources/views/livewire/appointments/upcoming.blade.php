<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" wire:poll.60s.visible>
    <button
        class="icon-btn relative"
        type="button"
        aria-label="المواعيد القادمة"
        title="المواعيد القادمة"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
    >
        <x-ui.icon name="calendar-days" class="size-5" />
        @if ($this->appointments->isNotEmpty())
            <span class="absolute -left-1 -top-1 grid min-h-5 min-w-5 place-items-center rounded-full bg-[var(--brand)] px-1 text-[10px] font-black leading-none text-white ring-2 ring-[var(--topbar)]">
                {{ $this->appointments->count() > 99 ? '99+' : $this->appointments->count() }}
            </span>
        @endif
    </button>

    <div
        class="fixed left-3 right-3 top-19 z-50 max-h-[min(30rem,calc(100vh-6rem))] overflow-hidden rounded-3xl border border-[var(--line)] bg-[var(--surface)] shadow-2xl sm:absolute sm:left-0 sm:right-auto sm:top-12 sm:w-[22rem]"
        x-cloak
        x-show="open"
        x-transition.opacity
        x-on:click.outside="open = false"
    >
        <div class="border-b border-[var(--line)] px-4 py-3">
            <p class="font-black text-[var(--ink)]">المواعيد القادمة</p>
            <p class="mt-1 text-xs text-[var(--muted)]">أقرب موعد لكل معالج ظاهر</p>
        </div>

        <div class="max-h-[24rem] overflow-y-auto p-2">
            @forelse ($this->appointments as $appointment)
                <article class="flex items-center gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-[var(--surface-soft)]" wire:key="upcoming-appointment-{{ $appointment->id }}">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand-strong)]">
                        <x-ui.icon name="calendar-days" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-black text-[var(--ink)]">{{ $appointment->practitioner?->name }}</p>
                        <p class="truncate text-xs text-[var(--muted)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') ?: 'دون مريض محدد' }}</p>
                    </div>
                    <time class="shrink-0 text-xs font-black text-[var(--brand-strong)]" datetime="{{ $appointment->starts_at->toIso8601String() }}">{{ $appointment->starts_at->format('H:i') }}</time>
                </article>
            @empty
                <div class="empty-state m-2">لا توجد مواعيد قادمة.</div>
            @endforelse
        </div>
    </div>
</div>
