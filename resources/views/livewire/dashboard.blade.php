<div class="space-y-6">
    <header class="page-header">
        <div>
            <p class="page-kicker">نظرة اليوم</p>
            <h1>مرحباً، {{ auth()->user()->name }}</h1>
            <p>{{ now()->translatedFormat('l، j F Y') }} — ملخص حي لعمل العيادة.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('appointments.manage')
                <a class="btn btn-primary" href="{{ route('appointments.create') }}" wire:navigate>حجز موعد</a>
            @endcan
            <a class="btn btn-secondary" href="{{ route('appointments.calendar') }}" wire:navigate>فتح التقويم</a>
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="إحصاءات مواعيد اليوم">
        @foreach ([
            ['كل المواعيد', $todayStats->total ?? 0],
            ['المجدولة', $todayStats->scheduled ?? 0],
            ['الحضور', $todayStats->attended ?? 0],
            ['المكتملة', $todayStats->completed ?? 0],
            ['لم يحضر', $todayStats->no_show ?? 0],
            ['الملغاة', $todayStats->cancelled ?? 0],
        ] as [$label, $value])
            <article class="metric-card">
                <p class="text-xs font-bold text-[var(--muted)]">{{ $label }}</p>
                <p class="mt-3 text-3xl font-black text-[var(--ink)]">{{ number_format($value) }}</p>
            </article>
        @endforeach
    </section>

    @if ($mode === 'practitioner')
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['جلسات الأسبوع', $practitionerStats['week_sessions']],
                ['استحقاق الأسبوع', $practitionerStats['week_earnings']],
                ['المبالغ المدفوعة', $practitionerStats['paid']],
                ['الرصيد المرحّل', $practitionerStats['remaining']],
                ['المرضى', $practitionerStats['patients']],
            ] as [$label, $value])
                <article class="metric-card">
                    <p class="text-xs font-bold text-[var(--muted)]">{{ $label }}</p>
                    <p class="mt-3 text-2xl font-black text-[var(--ink)]">{{ number_format($value) }}</p>
                </article>
            @endforeach
        </section>
        <section class="app-card p-5 sm:p-6">
            <div class="mb-4 flex items-center justify-between gap-3"><div><p class="page-kicker">هذا الأسبوع</p><h2 class="mt-1 font-black text-[var(--ink)]">جدول مواعيدي</h2></div><a class="text-sm font-bold text-[var(--brand-strong)]" href="{{ route('appointments.calendar') }}" wire:navigate>فتح التقويم</a></div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($practitionerStats['week_schedule'] as $appointment)
                    <article class="status-surface {{ $appointment->status->badgeClass() }} rounded-2xl border p-4"><div class="flex justify-between gap-3"><time class="font-black text-[var(--brand-strong)]">{{ $appointment->starts_at->translatedFormat('l Y/m/d H:i') }}</time><x-ui.status-badge :status="$appointment->status" /></div><p class="mt-3 font-bold text-[var(--ink)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') }}</p><p class="mt-1 text-xs text-[var(--muted)]">{{ $appointment->room?->name }}</p></article>
                @empty
                    <div class="empty-state md:col-span-2 xl:col-span-3">لا توجد مواعيد ضمن هذا الأسبوع.</div>
                @endforelse
            </div>
        </section>
    @else
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['بانتظار الدخول', $operational['waiting']],
                ['بانتظار الدفع', $operational['needs_payment']],
                ['الغرف المشغولة', $operational['occupied_rooms'].' / '.$operational['active_rooms']],
                ['مرضى جدد', $operational['new_patients']],
            ] as [$label, $value])
                <article class="metric-card">
                    <p class="text-xs font-bold text-[var(--muted)]">{{ $label }}</p>
                    <p class="mt-3 text-2xl font-black text-[var(--ink)]">{{ $value }}</p>
                </article>
            @endforeach
        </section>
    @endif

    @if ($mode === 'admin')
        <section class="app-card p-5 sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="page-kicker">الأسبوع المالي الحالي</p>
                    <h2 class="mt-1 text-lg font-black text-[var(--ink)]">{{ $financialWeek->starts_on->format('Y/m/d') }} — {{ $financialWeek->ends_on->format('Y/m/d') }}</h2>
                </div>
                <a class="btn btn-secondary" href="{{ route('finance.weekly-closing') }}" wire:navigate>مراجعة الإغلاق</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['المقبوضات', data_get($financial, 'collections', 0)],
                    ['المستردات', data_get($financial, 'refunds', 0)],
                    ['المصروفات المؤثرة', data_get($financial, 'expenses', 0)],
                    ['صافي الأسبوع', data_get($financial, 'calculated_net', 0)],
                ] as [$label, $value])
                    <div class="rounded-2xl bg-[var(--surface-soft)] p-4">
                        <p class="text-xs font-bold text-[var(--muted)]">{{ $label }}</p>
                        <p class="mt-2 text-xl font-black text-[var(--ink)]">{{ number_format((int) $value) }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="app-card p-5">
                <h2 class="mb-4 font-black text-[var(--ink)]">أرصدة الصناديق</h2>
                <div class="space-y-3">
                    @forelse ($cashboxes as $cashbox)
                        <div class="flex items-center justify-between rounded-2xl border border-[var(--line)] p-4">
                            <span class="font-bold text-[var(--ink-soft)]">{{ $cashbox->name }}</span>
                            <span class="text-lg font-black text-[var(--ink)]">{{ number_format((int) $cashbox->balance) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">لم تُنشأ صناديق بعد.</div>
                    @endforelse
                </div>
            </div>
            <div class="app-card p-5">
                <h2 class="mb-4 font-black text-[var(--ink)]">الأطباء الأكثر نشاطاً هذا الأسبوع</h2>
                <div class="space-y-3">
                    @forelse ($topPractitioners as $row)
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-[var(--surface-soft)] p-4">
                            <div>
                                <p class="font-bold text-[var(--ink)]">{{ $row->practitioner?->name }}</p>
                                <p class="text-xs text-[var(--muted)]">{{ $row->sessions_count }} جلسة</p>
                            </div>
                            <span class="font-black text-[var(--brand-strong)]">{{ number_format((int) $row->earned_amount) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">لا توجد استحقاقات مسجلة لهذا الأسبوع.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="app-card p-5">
                <div class="mb-5"><p class="page-kicker">آخر 7 أيام</p><h2 class="mt-1 font-black text-[var(--ink)]">اتجاه المقبوضات النقدية</h2></div>
                @php($trendMaximum = max(1, (int) $trend->max('total')))
                <div class="flex h-52 items-end gap-2" aria-label="مخطط المقبوضات اليومية">
                    @foreach (range(6, 0) as $daysAgo)
                        @php($day = now()->subDays($daysAgo))
                        @php($amount = (int) data_get($trend->get($day->format('Y-m-d')), 'total', 0))
                        <div class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                            <span class="text-[10px] font-bold text-[var(--muted)]">{{ $amount > 0 ? number_format($amount) : '0' }}</span>
                            <div class="w-full rounded-t-xl bg-[var(--brand)]" style="height: {{ max(4, (int) round(($amount / $trendMaximum) * 145)) }}px"></div>
                            <span class="text-[10px] text-[var(--muted)]">{{ $day->translatedFormat('D') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="app-card p-5">
                <div class="mb-4 flex items-center justify-between gap-3"><h2 class="font-black text-[var(--ink)]">أحدث حركات الصندوق</h2><a class="text-sm font-bold text-[var(--brand-strong)]" href="{{ route('finance.cashboxes') }}" wire:navigate>السجل الكامل</a></div>
                <div class="space-y-2">
                    @forelse ($recentTransactions as $transaction)
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-[var(--surface-soft)] p-3">
                            <div class="min-w-0"><p class="truncate text-sm font-bold text-[var(--ink)]">{{ $transaction->description ?: $transaction->type->label() }}</p><p class="text-[10px] text-[var(--muted)]">{{ $transaction->cashbox?->name }} · {{ $transaction->occurred_at->format('Y/m/d H:i') }}</p></div>
                            <strong class="shrink-0 {{ $transaction->direction === App\Enums\TransactionDirection::In ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}">{{ $transaction->direction === App\Enums\TransactionDirection::In ? '+' : '−' }}{{ number_format($transaction->amount) }}</strong>
                        </div>
                    @empty<div class="empty-state">لا توجد حركات مالية بعد.</div>@endforelse
                </div>
            </div>
        </section>
    @endif

    <section class="app-card p-5 sm:p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="font-black text-[var(--ink)]">المواعيد القادمة اليوم</h2>
            <a class="text-sm font-bold text-[var(--brand-strong)]" href="{{ route('appointments.index') }}" wire:navigate>عرض الكل</a>
        </div>
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($upcomingAppointments as $appointment)
                <article class="status-surface {{ $appointment->status->badgeClass() }} rounded-2xl border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <time class="text-lg font-black text-[var(--brand-strong)]">{{ $appointment->starts_at->format('H:i') }}</time>
                        <x-ui.status-badge :status="$appointment->status" />
                    </div>
                    <p class="mt-3 font-bold text-[var(--ink)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') }}</p>
                    <p class="mt-1 text-xs text-[var(--muted)]">{{ $appointment->practitioner?->name }} · {{ $appointment->room?->name }}</p>
                </article>
            @empty
                <div class="empty-state md:col-span-2 xl:col-span-3">لا توجد مواعيد قادمة اليوم.</div>
            @endforelse
        </div>
    </section>
</div>
