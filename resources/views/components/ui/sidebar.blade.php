@props([
    'navigationGroups' => [],
    'mobile' => false,
])

<div {{ $attributes->class(['flex min-h-0 flex-1 flex-col']) }}>
    <div class="flex h-20 shrink-0 items-center px-5" data-sidebar-header>
        <x-ui.brand data-sidebar-brand />
    </div>

    <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-5" aria-label="التنقل الرئيسي">
        <div class="flex flex-col gap-6" data-sidebar-sections>
            @forelse ($navigationGroups as $group)
                @php
                    $groupIsActive = collect($group['items'])->contains(
                        fn (array $item): bool => request()->routeIs($item['active'] ?? $item['route'])
                    );
                @endphp
                <section class="nav-group" data-active="{{ $groupIsActive ? 'true' : 'false' }}" wire:key="navigation-group-{{ $loop->index }}">
                    <h2 class="nav-group-title" data-sidebar-text>
                        <span class="nav-group-marker" aria-hidden="true"></span>
                        {{ $group['label'] }}
                    </h2>

                    <div class="nav-group-items">
                        @foreach ($group['items'] as $item)
                            <x-ui.nav-link
                                :href="route($item['route'], $item['parameters'] ?? [])"
                                :active="request()->routeIs($item['active'] ?? $item['route'])"
                                wire:key="navigation-item-{{ $loop->parent->index }}-{{ $item['route'] }}"
                                x-on:click="navigationOpen = false"
                                :aria-label="$item['label']"
                                :title="$item['label']"
                            >
                                <span class="nav-link-icon"><x-ui.icon :name="$item['icon']" class="size-[1.15rem]" /></span>
                                <span class="truncate" data-sidebar-text>{{ $item['label'] }}</span>
                            </x-ui.nav-link>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="empty-state mx-1 px-4 py-6">
                    <x-ui.icon name="lock" class="mx-auto size-6" />
                    <p class="mt-2 text-xs" data-sidebar-text>لا توجد أقسام متاحة لهذا الحساب.</p>
                </div>
            @endforelse
        </div>
    </nav>

    <div class="shrink-0 border-t border-[var(--line)] p-3">
        @if (\Illuminate\Support\Facades\Route::has('logout'))
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav-link w-full text-[var(--danger)] hover:bg-[var(--danger-soft)]" aria-label="تسجيل الخروج" title="تسجيل الخروج">
                    <x-ui.icon name="logout" class="size-[1.15rem]" />
                    <span data-sidebar-text>تسجيل الخروج</span>
                </button>
            </form>
        @endif

        <p class="mt-2 px-3 text-[0.65rem] leading-5 text-[var(--muted)]" data-sidebar-text>
            بيانات المرضى سرية — استخدمها ضمن صلاحياتك فقط.
        </p>
    </div>
</div>
