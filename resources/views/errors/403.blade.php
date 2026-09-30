<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full antialiased">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>غير مصرح — {{ config('clinic.name') }}</title>

        <script>
            (() => {
                const theme = localStorage.getItem('clinic-theme') === 'dark' ? 'dark' : 'light';
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.colorScheme = theme;
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full items-center justify-center bg-[var(--canvas)] px-5 py-10 text-[var(--ink)]">
        <main class="w-full max-w-lg rounded-[var(--radius-card)] border border-[var(--line)] bg-[var(--surface)] p-7 text-center shadow-[var(--shadow-card)] sm:p-10">
            <p class="text-sm font-semibold text-[var(--primary)]">خطأ 403</p>
            <h1 class="mt-3 text-2xl font-bold sm:text-3xl">لا تملك صلاحية الوصول</h1>
            <p class="mt-3 text-sm leading-7 text-[var(--ink-muted)]">
                لا يسمح حسابك بفتح هذه الصفحة أو تنفيذ هذا الإجراء.
            </p>
            <a
                href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}"
                class="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--primary)] px-5 text-sm font-semibold text-white transition hover:bg-[var(--primary-hover)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--focus)]"
            >
                العودة إلى الصفحة السابقة
            </a>
        </main>
    </body>
</html>
