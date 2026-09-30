<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full antialiased">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (filled($clinicFaviconUrl ?? null))<link rel="icon" href="{{ $clinicFaviconUrl }}">@endif
        <meta name="theme-color" content="#f3f9fa" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#17191a" media="(prefers-color-scheme: dark)">

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
    </head>
    <body class="min-h-full bg-[var(--canvas)] text-[var(--ink)]">
        <div class="app-atmosphere" aria-hidden="true"></div>

        <main class="relative flex min-h-dvh items-center justify-center px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="absolute left-3 top-3 z-20 sm:left-6 sm:top-6">
                <x-ui.theme-toggle />
            </div>

            <section class="grid w-full max-w-6xl overflow-hidden rounded-[1.75rem] border border-[var(--line)] bg-[var(--surface-elevated)] shadow-[var(--app-shadow-floating)] sm:rounded-[2.25rem] lg:min-h-[42rem] lg:grid-cols-[minmax(0,1.05fr)_minmax(25rem,.85fr)]">
                <aside class="relative hidden overflow-hidden border-l border-[var(--line)] bg-[var(--surface-soft)] p-10 lg:flex lg:flex-col lg:justify-between xl:p-14">
                    <span class="absolute -right-20 -top-24 size-64 rounded-full border-[3rem] border-[var(--brand-soft)] opacity-80" aria-hidden="true"></span>
                    <span class="absolute -bottom-20 left-10 size-52 rounded-full border-[2.5rem] border-[var(--accent-soft)] opacity-70" aria-hidden="true"></span>

                    <div class="relative">
                        <x-ui.brand />

                        <div class="mt-16 max-w-xl">
                            <p class="page-kicker">مساحة عمل موثوقة</p>
                            <h1 class="mt-4 text-3xl font-bold leading-[1.55] tracking-tight text-[var(--ink)] xl:text-[2.55rem]">
                                إدارة يوم العيادة<br>بهدوء ووضوح.
                            </h1>
                            <p class="mt-5 max-w-lg text-sm leading-8 text-[var(--muted)]">
                                وصول آمن إلى المواعيد وملفات المرضى والجلسات والعمليات المالية، ضمن صلاحيات كل مستخدم.
                            </p>
                        </div>

                        <div class="mt-10 grid max-w-xl gap-3 sm:grid-cols-3">
                            @foreach ([
                                ['calendar', 'مواعيد منظمة'],
                                ['audit', 'صلاحيات دقيقة'],
                                ['chart', 'متابعة واضحة'],
                            ] as [$icon, $label])
                                <div class="flex items-center gap-2.5 rounded-2xl border border-[var(--line)] bg-[var(--surface)]/75 px-3 py-3 text-xs font-semibold text-[var(--ink-soft)] shadow-[var(--app-shadow-sm)]">
                                    <x-ui.icon :name="$icon" class="size-4 shrink-0 text-[var(--brand-strong)]" />
                                    <span>{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <p class="relative mt-12 text-xs leading-6 text-[var(--muted)]">
                        نظام داخلي خاص بموظفي {{ $clinicShortName ?? config('clinic.short_name') }}.
                        تُسجّل محاولات الدخول لحماية بيانات المرضى.
                    </p>
                </aside>

                <div class="flex min-w-0 items-center bg-[var(--surface-elevated)] px-4 py-7 sm:px-8 sm:py-10 lg:px-12 xl:px-14">
                    <div class="mx-auto w-full max-w-md">
                        <div class="mb-8 flex justify-center lg:hidden">
                            <x-ui.brand />
                        </div>

                        <x-ui.flash />
                        {{ $slot }}

                        <p class="mt-7 text-center text-[0.7rem] leading-6 text-[var(--muted)] lg:hidden">
                            نظام داخلي خاص بموظفي {{ $clinicShortName ?? config('clinic.short_name') }}
                            <span class="mx-1 text-[var(--line-strong)]">•</span>
                            اتصالك محمي بجلسة مشفّرة
                        </p>
                    </div>
                </div>
            </section>
        </main>

        @livewireScripts
    </body>
</html>
