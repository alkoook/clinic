<div class="space-y-6">
    <header class="page-header"><div><p class="page-kicker">تنظيم الرعاية</p><h1>التوفر والدوام</h1><p>اختر المعالج للاطلاع على دوامه والاستثناءات قبل الحجز.</p></div></header>
    <section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($practitioners as $practitioner)
            <article class="app-card p-5" wire:key="availability-{{ $practitioner->id }}">
                <span class="status-badge">{{ $practitioner->is_active ? 'نشط' : 'متوقف' }}</span>
                <h2 class="my-3 text-lg font-semibold">{{ $practitioner->name }}</h2>
                <p class="text-sm text-[var(--muted)]">{{ $practitioner->specialties->pluck('name')->join('، ') }}</p>
                <p class="my-4 text-sm">{{ $practitioner->availabilities_count }} فترات دوام أسبوعية</p>
                <a class="btn btn-secondary w-full" href="{{ route('availability.manage', $practitioner) }}" wire:navigate>عرض الدوام</a>
            </article>
        @empty<div class="empty-state">لا يوجد معالجون مسجلون بعد.</div>@endforelse
    </section>
    {{ $practitioners->links() }}
</div>
