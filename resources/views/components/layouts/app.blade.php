<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full antialiased">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (filled($clinicFaviconUrl ?? null))<link rel="icon" href="{{ $clinicFaviconUrl }}">@endif
        <meta name="theme-color" content="#FAF8F5" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#121418" media="(prefers-color-scheme: dark)">

        <title>{{ filled($title ?? null) ? $title.' — ' : '' }}{{ $clinicName ?? config('clinic.name') }}</title>

        <script>
            (() => {
                const storageKey = 'clinic-theme';
                const configuredTheme = @js($defaultTheme ?? 'light');
                let storedTheme = null;

                try {
                    storedTheme = localStorage.getItem(storageKey);
                } catch {}

                const theme = storedTheme === 'light' || storedTheme === 'dark'
                    ? storedTheme
                    : (configuredTheme === 'dark' ? 'dark' : 'light');

                if (storedTheme !== theme) {
                    try {
                        localStorage.setItem(storageKey, theme);
                    } catch {}
                }

                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('head')
    </head>
    <body
        class="min-h-full bg-[var(--canvas)] text-[var(--ink)]"
        x-data="{
            navigationOpen: false,
            sidebarCollapsed: false,
            init() {
                try {
                    this.sidebarCollapsed = localStorage.getItem('clinic-sidebar-collapsed') === 'true';
                } catch {}
            },
            toggleSidebar() {
                this.sidebarCollapsed = ! this.sidebarCollapsed;

                try {
                    localStorage.setItem('clinic-sidebar-collapsed', String(this.sidebarCollapsed));
                } catch {}
            },
        }"
        x-on:keydown.escape.window="navigationOpen = false"
    >
        <x-ui.print-brand-header />

        <a href="#main-content" class="sr-only z-[60] rounded-xl bg-[var(--surface)] px-4 py-2 font-bold text-[var(--ink)] focus:not-sr-only focus:fixed focus:right-4 focus:top-4">
            الانتقال إلى المحتوى
        </a>

        <div class="app-atmosphere" aria-hidden="true"></div>

        <aside
            class="app-sidebar fixed inset-y-0 right-0 z-40 hidden border-l border-[var(--line)] bg-[var(--sidebar)] lg:flex lg:flex-col"
            x-bind:data-collapsed="sidebarCollapsed ? 'true' : 'false'"
        >
            <x-ui.sidebar :navigation-groups="$navigationGroups ?? []" />
        </aside>

        <div
            class="fixed inset-0 z-50 lg:hidden"
            x-cloak
            x-show="navigationOpen"
            role="dialog"
            aria-modal="true"
            aria-label="قائمة التنقل"
        >
            <button
                type="button"
                class="absolute inset-0 bg-slate-950/45 backdrop-blur-[2px]"
                aria-label="إغلاق قائمة التنقل"
                x-on:click="navigationOpen = false"
                x-show="navigationOpen"
                x-transition.opacity
            ></button>

            <aside
                class="absolute inset-y-0 right-0 flex w-[min(88vw,20rem)] flex-col border-l border-[var(--line)] bg-[var(--sidebar)] shadow-2xl"
                x-show="navigationOpen"
                x-transition:enter="motion-safe:transition motion-safe:duration-200 motion-safe:ease-out"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="motion-safe:transition motion-safe:duration-150 motion-safe:ease-in"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
            >
                <button
                    type="button"
                    class="icon-btn absolute left-3 top-3 z-10"
                    aria-label="إغلاق قائمة التنقل"
                    x-on:click="navigationOpen = false"
                >
                    <x-ui.icon name="close" class="size-5" />
                </button>

                <x-ui.sidebar :navigation-groups="$navigationGroups ?? []" mobile />
            </aside>
        </div>

        <div
            class="app-shell-content relative min-h-screen"
            x-bind:data-sidebar-collapsed="sidebarCollapsed ? 'true' : 'false'"
        >
            <header class="app-topbar sticky z-30">
                <div class="flex h-18 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="mobile-navigation-toggle icon-btn"
                        aria-label="فتح قائمة التنقل"
                        x-on:click="navigationOpen = true"
                    >
                        <x-ui.icon name="menu" class="size-5" />
                    </button>

                    <button
                        type="button"
                        class="desktop-sidebar-toggle icon-btn"
                        x-on:click="toggleSidebar()"
                        x-bind:aria-label="sidebarCollapsed ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية'"
                        x-bind:title="sidebarCollapsed ? 'توسيع القائمة الجانبية' : 'طي القائمة الجانبية'"
                    >
                        <x-ui.icon name="menu" class="size-5" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="topbar-eyebrow truncate">مساحة العمل الآمنة</p>
                        <h1 class="truncate text-base font-bold text-[var(--ink)] sm:text-lg">{{ $title ?? 'لوحة العيادة' }}</h1>
                    </div>

                    <livewire:notifications.bell />

                    <livewire:appointments.upcoming />

                    <x-ui.theme-toggle />

                    @php
                        $authenticatedUser = auth()->user();
                        $userInitial = $authenticatedUser ? \Illuminate\Support\Str::substr($authenticatedUser->name, 0, 1) : '؟';
                    @endphp

                    @if ($authenticatedUser)
                        <div class="hidden min-w-0 items-center gap-3 border-r border-[var(--line)] pr-4 sm:flex">
                            <div class="grid size-10 shrink-0 place-items-center rounded-2xl bg-[var(--brand-soft)] text-sm font-extrabold text-[var(--brand-strong)]">
                                {{ $userInitial }}
                            </div>
                            <div class="min-w-0 leading-tight">
                                <p class="max-w-44 truncate text-sm font-bold text-[var(--ink)]">{{ $authenticatedUser->name }}</p>
                                <p class="mt-1 max-w-44 truncate text-xs text-[var(--muted)]">{{ $authenticatedUser->role?->label ?? 'مستخدم العيادة' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </header>

            <main id="main-content" class="page-enter relative px-4 py-6 sm:px-6 sm:py-8 lg:px-8 lg:py-9">
                <div class="mx-auto w-full max-w-[94rem]">
                    <x-ui.flash />
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-ui.confirm-dialog />

        @livewireScripts
        @stack('scripts')
    </body>
</html>
