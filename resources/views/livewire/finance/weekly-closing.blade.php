<div class="space-y-6">
    <header class="page-header">
        <div>
            <p class="page-kicker">تثبيت النتائج</p>
            <h1>إغلاق الأسبوع المالي</h1>
            <p>راجع التحصيل والمصاريف والمستحقات قبل تثبيت الأرقام وإنشاء تسويات المعالجين.</p>
        </div>
        <label class="w-full sm:w-80">
            <span class="form-label">الأسبوع المالي</span>
            <select class="form-select" wire:model.live="financialWeekId">
                @foreach ($this->weeks as $weekOption)
                    <option value="{{ $weekOption->id }}">
                        {{ $weekOption->starts_on->format('Y/m/d') }} — {{ $weekOption->ends_on->format('Y/m/d') }} ({{ $weekOption->status->label() }})
                    </option>
                @endforeach
            </select>
            @error('financialWeekId')<span class="form-error">{{ $message }}</span>@enderror
        </label>
    </header>

    <section class="app-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-black text-[var(--ink)]">من {{ $this->week->starts_on->format('Y/m/d') }} إلى {{ $this->week->ends_on->format('Y/m/d') }}</h2>
                <span class="status-badge {{ $this->week->status === App\Enums\FinancialWeekStatus::Closed ? 'status-danger' : 'status-success' }}">{{ $this->week->status->label() }}</span>
            </div>
            <p class="mt-2 text-xs leading-6 text-[var(--muted)]">الأسبوع من السبت إلى الخميس، وأي حركة يوم الجمعة تُسند إلى الأسبوع التالي.</p>
        </div>
        @if ($this->week->closing)
            <div class="rounded-2xl bg-[var(--success-soft)] px-4 py-3 text-sm font-bold text-[var(--success)]">
                أُغلق في {{ $this->week->closing->closed_at->format('Y/m/d H:i') }}
            </div>
        @endif
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="ملخص الأسبوع">
        @foreach ([
            ['الجلسات المدفوعة', $this->preview['paid_sessions_count'], '', 'جلسة'],
            ['إجمالي التحصيل', $this->preview['collections'], 'text-[var(--success)]', 'ل.س'],
            ['حصص المعالجين', $this->preview['practitioner_shares'], 'text-[var(--warning)]', 'ل.س'],
            ['المصاريف المحتسبة', $this->preview['expenses'], 'text-[var(--danger)]', 'ل.س'],
            ['الاستردادات', $this->preview['refunds'], 'text-[var(--danger)]', 'ل.س'],
            ['أثر التعديلات', $this->preview['adjustments'], '', 'ل.س'],
            ['النقد المتاح بالرئيسي', $this->preview['available_cash'], 'text-[var(--brand-strong)]', 'ل.س'],
            ['التزامات المعالجين', $this->preview['reserved_practitioner_liabilities'], 'text-[var(--warning)]', 'ل.س'],
        ] as [$label, $value, $color, $suffix])
            <article class="metric-card">
                <p class="text-xs font-bold text-[var(--muted)]">{{ $label }}</p>
                <p class="mt-3 text-2xl font-black {{ $color ?: 'text-[var(--ink)]' }}" dir="ltr">{{ number_format($value) }} <span class="text-xs">{{ $suffix }}</span></p>
            </article>
        @endforeach
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <article class="app-card p-5 lg:col-span-1">
            <p class="text-xs font-bold text-[var(--muted)]">صافي نتيجة الأسبوع</p>
            <p class="mt-3 text-3xl font-black {{ $this->preview['calculated_net'] < 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]' }}" dir="ltr">{{ number_format($this->preview['calculated_net']) }} <span class="text-sm">ل.س</span></p>
            <p class="mt-3 text-xs leading-6 text-[var(--muted)]">التحصيل ناقص حصص المعالجين والمصاريف والاستردادات، مع أثر التعديلات.</p>
        </article>
        <article class="app-card p-5">
            <p class="text-xs font-bold text-[var(--muted)]">الفائض القابل للتحويل</p>
            <p class="mt-3 text-3xl font-black text-[var(--brand-strong)]" dir="ltr">{{ number_format($this->preview['transferable_surplus']) }} <span class="text-sm">ل.س</span></p>
            <p class="mt-3 text-xs leading-6 text-[var(--muted)]">لا يتجاوز النقد المتاح بعد حجز مستحقات المعالجين.</p>
        </article>
        <article class="app-card p-5">
            <p class="text-xs font-bold text-[var(--muted)]">العجز القابل للتغطية</p>
            <p class="mt-3 text-3xl font-black text-[var(--danger)]" dir="ltr">{{ number_format($this->preview['deficit_coverable']) }} <span class="text-sm">ل.س</span></p>
            <p class="mt-3 text-xs leading-6 text-[var(--muted)]">يُغطّى من الصندوق الجانبي بحد رصيده المتاح.</p>
        </article>
    </section>

    <section class="space-y-4">
        <div><p class="page-kicker">تفصيل الالتزامات</p><h2 class="mt-1 text-lg font-black text-[var(--ink)]">مستحقات المعالجين</h2></div>
        <div class="table-shell">
            <table>
                <thead><tr><th>المعالج</th><th>رصيد افتتاحي</th><th>أرباح الأسبوع</th><th>تعديلات</th><th>إجمالي المستحق</th><th>المدفوع</th><th>المتبقي</th></tr></thead>
                <tbody>
                    @forelse ($this->preview['practitioner_breakdown'] as $row)
                        <tr wire:key="weekly-breakdown-{{ $row['practitioner_id'] }}">
                            <td class="font-bold text-[var(--ink)]">{{ $row['practitioner_name'] ?: 'معالج #'.$row['practitioner_id'] }}</td>
                            <td>{{ number_format($row['opening_balance']) }} ل.س</td>
                            <td>{{ number_format($row['earnings_amount']) }} ل.س</td>
                            <td class="{{ $row['adjustments_amount'] < 0 ? 'text-[var(--danger)]' : '' }}">{{ number_format($row['adjustments_amount']) }} ل.س</td>
                            <td class="font-bold text-[var(--ink)]">{{ number_format($row['total_due']) }} ل.س</td>
                            <td>{{ number_format($row['paid_amount']) }} ل.س</td>
                            <td class="font-black {{ $row['remaining_amount'] > 0 ? 'text-[var(--warning)]' : 'text-[var(--success)]' }}">{{ number_format($row['remaining_amount']) }} ل.س</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state">لا توجد مستحقات معالجين في هذا الأسبوع.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($this->week->status === App\Enums\FinancialWeekStatus::Open)
        <form class="app-card p-5 sm:p-6" wire:submit="recordAdjustment">
            <div class="mb-4"><p class="page-kicker">تصحيح موثّق</p><h2 class="mt-1 font-black text-[var(--ink)]">تعديل مالي يدوي</h2><p class="mt-1 text-xs text-[var(--muted)]">استخدم قيمة موجبة للإضافة وسالبة للحسم. لا تُعدّل السجلات المالية السابقة.</p></div>
            <div class="grid gap-4 lg:grid-cols-[1fr_1fr_2fr_auto] lg:items-end"><label><span class="form-label">المعالج (اختياري)</span><select class="form-select" wire:model="adjustmentPractitionerId"><option value="">العيادة</option>@foreach ($this->practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>@endforeach</select></label><label><span class="form-label">القيمة *</span><input class="form-input" type="number" step="1" wire:model="adjustmentAmount">@error('adjustmentAmount')<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">السبب (اختياري)</span><input class="form-input" wire:model="adjustmentReason">@error('adjustmentReason')<span class="form-error">{{ $message }}</span>@enderror</label><button class="btn btn-secondary" type="submit" wire:loading.attr="disabled">تسجيل التعديل</button></div>
        </form>
    @endif

    @if ($this->canCloseWeek)
        <form class="app-card border-[color-mix(in_srgb,var(--danger)_22%,transparent)] p-5 sm:p-6" wire:submit="close">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <label class="flex max-w-2xl items-start gap-3">
                    <input class="form-checkbox mt-1" type="checkbox" wire:model="confirmed">
                    <span><span class="block font-black text-[var(--ink)]">راجعت الأرقام وأوافق على إغلاق هذه الفترة</span><span class="mt-1 block text-xs leading-6 text-[var(--muted)]">يمكن للمدير الإغلاق في أي يوم. تُثبت اللقطة المالية وتُنشأ التسويات وتُرحّل الأرصدة عند الحاجة.</span>@error('confirmed')<span class="form-error">{{ $message }}</span>@enderror</span>
                </label>
                <button class="btn btn-danger shrink-0" type="button" wire:loading.attr="disabled" wire:target="close" x-on:click="$dispatch('confirm-action', { title: 'إغلاق الأسبوع نهائيًا؟', description: 'سيتم تثبيت الأرقام وإنشاء التسويات وترحيل الفائض أو تغطية العجز. لا يمكن التراجع عن الإغلاق.', confirmLabel: 'إغلاق الأسبوع', action: () => $wire.close() })">
                    <span wire:loading.remove wire:target="close">إغلاق الأسبوع نهائياً</span><span wire:loading wire:target="close">جارٍ الإغلاق…</span>
                </button>
            </div>
        </form>
    @elseif ($this->week->status === App\Enums\FinancialWeekStatus::Closed)
        <section class="app-card p-5 sm:p-6"><div class="flash-message flash-success">هذه الفترة مغلقة وأرقامها محفوظة كلقطة مالية ثابتة.</div><label class="mt-4 block"><span class="form-label">سبب إعادة الفتح (اختياري)</span><input class="form-input" wire:model="reopenReason"></label><div class="mt-4 flex flex-wrap gap-3"><button class="btn btn-secondary" type="button" x-on:click="$dispatch('confirm-action', { title: 'إعادة فتح الفترة المالية؟', description: 'سيُسجل الحدث في سجل التدقيق. قد يمنع النظام العملية إذا وُجدت حركات أو تسويات لاحقة تعتمد على الإغلاق.', confirmLabel: 'إعادة فتح الفترة', action: () => $wire.reopen() })">إعادة فتح الأسبوع</button><button class="btn btn-primary" type="button" wire:click="openNew">فتح أسبوع مالي جديد</button></div></section>
    @endif
</div>
