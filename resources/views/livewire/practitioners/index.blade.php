<div class="space-y-5">
    <header class="page-header">
        <div><p class="page-kicker">فريق العيادة</p><h1>الأطباء والمعالجون</h1><p>إدارة الحسابات والاختصاصات والتوفر والاستحقاقات.</p></div>
        <a class="btn btn-primary" href="{{ route('practitioners.create') }}" wire:navigate>إضافة معالج</a>
    </header>
    <section class="app-card grid gap-3 p-4 md:grid-cols-3">
        <label class="md:col-span-2"><span class="form-label">البحث</span><input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="الاسم أو الهاتف أو اسم المستخدم"></label>
        <label><span class="form-label">الحالة</span><select class="form-select" wire:model.live="active"><option value="">الكل</option><option value="1">نشط</option><option value="0">متوقف</option></select></label>
    </section>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->practitioners as $practitioner)
            <article class="app-card p-5" wire:key="practitioner-{{ $practitioner->id }}">
                <div class="flex items-start justify-between gap-3"><div><h2 class="font-black text-[var(--ink)]">{{ $practitioner->name }}</h2><p class="mt-1 text-xs text-[var(--muted)]">{{ $practitioner->specialties->pluck('name')->join('، ') ?: 'دون اختصاص محدد' }}</p></div><button class="status-badge {{ $practitioner->is_active ? 'status-success' : 'status-danger' }}" type="button" wire:click="toggle({{ $practitioner->id }})">{{ $practitioner->is_active ? 'نشط' : 'متوقف' }}</button></div>
                <dl class="my-5 grid grid-cols-2 gap-3 text-center"><div class="rounded-xl bg-[var(--surface-soft)] p-3"><dt class="text-xs text-[var(--muted)]">المواعيد</dt><dd class="mt-1 font-black text-[var(--ink)]">{{ $practitioner->appointments_count }}</dd></div><div class="rounded-xl bg-[var(--surface-soft)] p-3"><dt class="text-xs text-[var(--muted)]">المرضى</dt><dd class="mt-1 font-black text-[var(--ink)]">{{ $practitioner->patients_count }}</dd></div></dl>
                <div class="flex gap-2"><a class="btn btn-secondary flex-1" href="{{ route('practitioners.show', $practitioner) }}" wire:navigate>الملف</a><a class="btn btn-secondary" href="{{ route('practitioners.edit', $practitioner) }}" wire:navigate>تعديل</a><a class="btn btn-secondary" href="{{ route('availability.manage', $practitioner) }}" wire:navigate>التوفر</a></div>
            </article>
        @empty
            <div class="empty-state sm:col-span-2 xl:col-span-3">لم يُضف أي معالج بعد.</div>
        @endforelse
    </div>
    {{ $this->practitioners->links() }}
</div>
