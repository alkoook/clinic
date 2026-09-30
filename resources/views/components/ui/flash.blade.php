@php
    $messages = [
        'success' => ['icon' => 'check', 'class' => 'flash-success'],
        'error' => ['icon' => 'warning', 'class' => 'flash-error'],
        'warning' => ['icon' => 'warning', 'class' => 'flash-warning'],
        'status' => ['icon' => 'check', 'class' => 'flash-success'],
    ];
@endphp

<div
    x-data="{
        notices: [],
        push(detail) {
            const id = `${Date.now()}-${Math.random()}`;
            this.notices.push({ id, type: detail.type || 'success', message: detail.message });
            window.setTimeout(() => this.remove(id), 5000);
        },
        remove(id) {
            this.notices = this.notices.filter((notice) => notice.id !== id);
        },
    }"
    x-on:app-toast.window="push($event.detail)"
>
    <div class="mb-5 flex flex-col gap-3 empty:hidden" aria-live="polite">
        @foreach ($messages as $key => $presentation)
            @if (session()->has($key))
                <div
                    class="flash-message {{ $presentation['class'] }}"
                    role="{{ $key === 'error' ? 'alert' : 'status' }}"
                    x-data="{ visible: true }"
                    x-show="visible"
                    wire:key="flash-{{ $key }}"
                >
                    <x-ui.icon :name="$presentation['icon']" class="size-5 shrink-0" />
                    <p class="min-w-0 flex-1 text-sm font-semibold">{{ session($key) }}</p>
                    <button type="button" class="rounded-lg p-1 hover:bg-black/5" aria-label="إخفاء الرسالة" x-on:click="visible = false">
                        <x-ui.icon name="close" class="size-4" />
                    </button>
                </div>
            @endif
        @endforeach
    </div>

    <div class="pointer-events-none fixed inset-x-3 bottom-3 z-[80] flex flex-col items-start gap-2 sm:inset-x-auto sm:bottom-5 sm:left-5 sm:w-[min(24rem,calc(100vw-2.5rem))]" aria-live="polite">
        <template x-for="notice in notices" x-bind:key="notice.id">
            <div
                class="flash-message pointer-events-auto w-full shadow-[var(--app-shadow-floating)]"
                x-bind:class="notice.type === 'error' ? 'flash-error' : (notice.type === 'warning' ? 'flash-warning' : 'flash-success')"
                x-transition:enter="motion-safe:transition motion-safe:duration-200 motion-safe:ease-out"
                x-transition:enter-start="translate-y-3 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="translate-y-2 opacity-0"
                x-bind:role="notice.type === 'error' ? 'alert' : 'status'"
            >
                <span class="size-2.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
                <p class="min-w-0 flex-1 text-sm font-semibold" x-text="notice.message"></p>
                <button type="button" class="rounded-lg p-1 hover:bg-black/5" aria-label="إخفاء الرسالة" x-on:click="remove(notice.id)">
                    <x-ui.icon name="close" class="size-4" />
                </button>
            </div>
        </template>
    </div>
</div>
