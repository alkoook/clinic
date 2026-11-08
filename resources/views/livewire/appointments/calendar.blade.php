<div class="space-y-5">
    <header class="page-header"><div><p class="page-kicker">رؤية زمنية</p><h1>تقويم المواعيد</h1><p>عرض يومي أو أسبوعي يبدأ يوم السبت.</p></div><div class="flex gap-2">@can('appointments.manage')<a class="btn btn-primary" href="{{ route('appointments.create') }}" wire:navigate>موعد جديد</a>@endcan<a class="btn btn-secondary" href="{{ route('appointments.index') }}" wire:navigate>عرض القائمة</a></div></header>
    <section class="app-card space-y-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2"><button class="btn btn-secondary" type="button" wire:click="move(-1)" aria-label="السابق">السابق</button><button class="btn btn-secondary" type="button" wire:click="today">اليوم</button><button class="btn btn-secondary" type="button" wire:click="move(1)" aria-label="التالي">التالي</button></div>
            <strong class="text-[var(--ink)]">{{ $this->range[0]->translatedFormat('j F') }} — {{ $this->range[1]->translatedFormat('j F Y') }}</strong>
            <div class="flex rounded-2xl bg-[var(--surface-soft)] p-1"><button class="btn {{ $viewMode === 'day' ? 'btn-primary' : 'btn-secondary' }}" type="button" wire:click="$set('viewMode', 'day')">يومي</button><button class="btn {{ $viewMode === 'week' ? 'btn-primary' : 'btn-secondary' }}" type="button" wire:click="$set('viewMode', 'week')">أسبوعي</button></div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @unless (auth()->user()->hasRole('practitioner'))<select class="form-select" wire:model.live="practitionerId"><option value="">كل المعالجين</option>@foreach ($this->practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>@endforeach</select>@endunless
            <select class="form-select" wire:model.live="roomId"><option value="">كل الغرف</option>@foreach ($this->rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select>
            <select class="form-select" wire:model.live="treatmentTypeId"><option value="">كل العلاجات</option>@foreach ($this->treatmentTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select>
            <select class="form-select" wire:model.live="status"><option value="">كل الحالات</option>@foreach (App\Enums\AppointmentStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
            <input class="form-input" type="date" wire:model.live="focusDate">
        </div>
    </section>
    @php($calendarAppointments = $this->appointments->groupBy(fn ($appointment) => $appointment->starts_at->format('Y-m-d')))
    <section class="grid gap-4 {{ $viewMode === 'week' ? 'md:grid-cols-2 xl:grid-cols-4' : 'mx-auto max-w-5xl' }}">
        @foreach (range(0, $viewMode === 'day' ? 0 : 6) as $offset)
            @php($day = $this->range[0]->addDays($offset))
            <article class="app-card min-h-56 overflow-hidden p-4 sm:p-5 {{ $day->isToday() ? 'ring-2 ring-[var(--brand)]' : '' }}">
                <div class="mb-4 flex items-center justify-between"><div><p class="text-xs font-bold text-[var(--muted)]">{{ $day->translatedFormat('l') }}</p><h2 class="text-lg font-black text-[var(--ink)]">{{ $day->format('d/m') }}</h2></div><span class="status-badge">{{ $calendarAppointments->get($day->format('Y-m-d'), collect())->count() }}</span></div>
                <div class="space-y-3">
                    @forelse ($calendarAppointments->get($day->format('Y-m-d'), collect()) as $appointment)
                        <article class="status-surface {{ $appointment->status->badgeClass() }} group rounded-2xl border p-3 transition hover:-translate-y-0.5 hover:shadow-md" wire:key="calendar-{{ $appointment->id }}">
                            <div class="flex items-start justify-between gap-2"><div><time class="font-black text-[var(--brand-strong)]" dir="ltr">{{ $appointment->starts_at->format('H:i') }} – {{ $appointment->ends_at->format('H:i') }}</time><p class="mt-0.5 text-[10px] font-bold text-[var(--muted)]">{{ $appointment->duration_minutes }} دقيقة</p></div><x-ui.status-badge :status="$appointment->status" /></div>
                            <p class="mt-3 text-sm font-black text-[var(--ink)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') }}</p>
                            <p class="mt-1 text-xs font-bold text-[var(--ink-soft)]">{{ $appointment->treatmentType?->name }}</p>
                            <p class="mt-2 text-xs text-[var(--muted)]">{{ $appointment->practitioner?->name }} · {{ $appointment->room?->name }}</p>
                            @can('appointments.manage')<a class="mt-3 inline-flex text-xs font-black text-[var(--brand-strong)]" href="{{ route('appointments.reschedule', $appointment) }}" wire:navigate>فتح وإعادة الجدولة</a>@endcan
                        </article>
                    @empty<div class="empty-state py-8">لا مواعيد</div>@endforelse
                </div>
            </article>
        @endforeach
    </section>
</div>
