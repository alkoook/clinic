<div class="space-y-6">
    @error('operation')<div class="flash-message flash-error" role="alert">{{ $message }}</div>@enderror
    <header class="page-header">
        <div>
            <p class="page-kicker">ذمم المعالجين</p>
            <h1>مستحقات الأطباء والمعالجين</h1>
            <p>تسويات أسبوعية تحفظ الرصيد المرحّل، والأرباح، والمدفوع، والمتبقي.</p>
        </div>
    </header>

    <section class="app-card grid gap-4 p-4 sm:grid-cols-2">
        @unless (auth()->user()->hasRole('practitioner'))
            <label>
                <span class="form-label">الطبيب / المعالج</span>
                <select class="form-select" wire:model.live="practitionerId">
                    <option value="">كل المعالجين</option>
                    @foreach ($this->practitioners as $practitioner)
                        <option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
        <label>
            <span class="form-label">حالة التسوية</span>
            <select class="form-select" wire:model.live="status">
                <option value="">كل الحالات</option>
                @foreach (App\Enums\SettlementStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </label>
    </section>

    @can('settlements.manage')
        @if ($practitionerId)
            <section class="app-card flex flex-wrap items-end justify-between gap-4 p-4">
                <div><p class="page-kicker">تسوية يومية</p><p class="text-sm text-[var(--muted)]">الرصيد غير المدفوع الآن: <strong class="text-[var(--warning)]">{{ number_format($this->currentUnpaidBalance) }} ل.س</strong></p></div>
                <button class="btn btn-primary" type="button" @disabled($this->currentUnpaidBalance <= 0) wire:click="prepareDailySettlement({{ $practitionerId }})">تسوية الرصيد الآن</button>
            </section>
        @endif
    @endcan

    @if ($dailyPractitionerId)
        <form class="app-card p-5 sm:p-6" wire:submit="settleDaily">
            <div class="grid items-end gap-4 md:grid-cols-[1fr_16rem_auto]"><div><p class="page-kicker">تسوية يومية</p><p class="text-sm text-[var(--muted)]">يمكنك دفع كامل الرصيد أو جزء منه.</p></div><label><span class="form-label">المبلغ (ل.س)</span><input class="form-input" type="number" min="1" wire:model="dailyAmount">@error('dailyAmount')<span class="form-error">{{ $message }}</span>@enderror</label><div class="flex gap-2"><button class="btn btn-secondary" type="button" wire:click="$set('dailyPractitionerId', null)">إلغاء</button><button class="btn btn-primary" type="submit" wire:loading.attr="disabled">تأكيد التسوية</button></div></div>
        </form>
    @endif

    @if ($payingSettlementId)
        @php($selectedSettlement = $this->settlements->getCollection()->firstWhere('id', $payingSettlementId))
        <form class="app-card p-5 sm:p-6" wire:submit="pay">
            <div class="grid items-end gap-4 lg:grid-cols-[minmax(0,1fr)_16rem_auto]">
                <div>
                    <p class="page-kicker">دفعة مستحق</p>
                    <h2 class="mt-1 text-lg font-black text-[var(--ink)]">{{ $selectedSettlement?->practitioner?->name ?: 'التسوية #'.$payingSettlementId }}</h2>
                    <p class="mt-2 text-xs text-[var(--muted)]">المتبقي قبل هذه الدفعة: <strong class="text-[var(--warning)]">{{ number_format($selectedSettlement?->remaining_amount ?? 0) }} ل.س</strong></p>
                </div>
                <label>
                    <span class="form-label">قيمة الدفعة (ل.س) *</span>
                    <input class="form-input" type="number" min="1" step="1" inputmode="numeric" wire:model="paymentAmount">
                    @error('paymentAmount')<span class="form-error">{{ $message }}</span>@enderror
                    @error('payingSettlementId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <div class="flex gap-2">
                    <button class="btn btn-secondary" type="button" wire:click="$set('payingSettlementId', null)">إلغاء</button>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="pay"><span wire:loading.remove wire:target="pay">دفع</span><span wire:loading wire:target="pay">جارٍ الدفع…</span></button>
                </div>
            </div>
        </form>
    @endif

    <div class="table-shell">
        <table>
            <thead><tr><th>المعالج</th><th>الأسبوع</th><th>الرصيد المرحّل</th><th>أرباح الأسبوع</th><th>التعديلات</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
                @forelse ($this->settlements as $settlement)
                    @php($statusClass = $settlement->status === App\Enums\SettlementStatus::Paid ? 'status-success' : ($settlement->status === App\Enums\SettlementStatus::PartiallyPaid ? 'status-warning' : 'status-danger'))
                    <tr wire:key="settlement-{{ $settlement->id }}">
                        <td class="font-bold text-[var(--ink)]">{{ $settlement->practitioner->name }}</td>
                        <td class="whitespace-nowrap">
                            <p>{{ $settlement->financialWeek->starts_on->format('Y/m/d') }}</p>
                            <p class="text-xs text-[var(--muted)]">حتى {{ $settlement->financialWeek->ends_on->format('Y/m/d') }}</p>
                        </td>
                        <td>{{ number_format($settlement->opening_balance) }} ل.س</td>
                        <td>{{ number_format($settlement->earnings_amount) }} ل.س</td>
                        <td class="{{ $settlement->adjustments_amount < 0 ? 'text-[var(--danger)]' : '' }}">{{ number_format($settlement->adjustments_amount) }} ل.س</td>
                        <td class="font-bold text-[var(--ink)]">{{ number_format($settlement->total_due) }} ل.س</td>
                        <td class="text-[var(--success)]">{{ number_format($settlement->paid_amount) }} ل.س</td>
                        <td class="font-black text-[var(--warning)]">{{ number_format($settlement->remaining_amount) }} ل.س</td>
                        <td><span class="status-badge {{ $statusClass }}">{{ $settlement->status->label() }}</span></td>
                        <td>
                            <button class="btn btn-secondary" type="button" wire:click="viewStatement({{ $settlement->id }})">كشف تفصيلي</button>
                            @can('settlements.manage')
                                @if ($settlement->remaining_amount > 0)
                                    <button class="btn btn-secondary" type="button" wire:click="preparePayment({{ $settlement->id }})">تسجيل دفعة</button>
                                @else
                                    <span class="text-xs text-[var(--muted)]">مكتملة</span>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10"><div class="empty-state">لا توجد تسويات مطابقة. تُنشأ التسويات عند إغلاق الأسبوع المالي.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $this->settlements->links() }}

    @if ($this->statementSettlement)
        <section class="app-card p-5 sm:p-6 print:shadow-none" id="practitioner-statement">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><p class="page-kicker">كشف أسبوعي تفصيلي</p><h2 class="mt-1 text-xl font-black text-[var(--ink)]">{{ $this->statementSettlement->practitioner->name }}</h2><p class="mt-1 text-sm text-[var(--muted)]">{{ $this->statementSettlement->financialWeek->starts_on->format('Y/m/d') }} — {{ $this->statementSettlement->financialWeek->ends_on->format('Y/m/d') }}</p></div><div class="flex gap-2 print:hidden"><button class="btn btn-secondary" type="button" x-on:click="window.print()">طباعة الكشف</button><button class="btn btn-secondary" type="button" wire:click="$set('statementSettlementId', null)">إغلاق</button></div></div>
            <div class="table-shell shadow-none"><table><thead><tr><th>التاريخ</th><th>المريض</th><th>العلاج</th><th>السعر</th><th>الخصم</th><th>الصافي</th><th>النسبة</th><th>حصة المعالج</th><th>الحالة</th></tr></thead><tbody>@forelse ($this->statementEarnings as $earning)@php($participant = $earning->treatmentSessionParticipant)<tr><td>{{ $earning->earned_at->format('Y/m/d H:i') }}</td><td class="font-bold text-[var(--ink)]">{{ $participant?->patient_name }}</td><td>{{ $participant?->treatmentSession?->treatment_type_name }}</td><td>{{ number_format($participant?->original_price ?? 0) }}</td><td>{{ number_format($participant?->discount_amount ?? 0) }}</td><td>{{ number_format($earning->gross_amount) }}</td><td>{{ $earning->practitioner_percentage }}%</td><td class="font-black">{{ number_format($earning->amount) }}</td><td>{{ $participant?->payment_status?->label() }}</td></tr>@empty<tr><td colspan="9"><div class="empty-state">لا توجد جلسات مدفوعة في هذا الكشف.</div></td></tr>@endforelse</tbody></table></div>
            @if ($this->statementAdjustments->isNotEmpty())<div class="mt-5"><h3 class="mb-3 font-black text-[var(--ink)]">التعديلات</h3><div class="space-y-2">@foreach ($this->statementAdjustments as $adjustment)<div class="flex justify-between gap-3 rounded-xl bg-[var(--surface-soft)] p-3"><div><p class="font-bold text-[var(--ink)]">{{ $adjustment->reason }}</p><p class="text-xs text-[var(--muted)]">{{ $adjustment->occurred_at->format('Y/m/d H:i') }}</p></div><strong class="{{ $adjustment->amount < 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]' }}">{{ number_format($adjustment->amount) }} ل.س</strong></div>@endforeach</div></div>@endif
            <div class="mt-5 grid gap-3 sm:grid-cols-3"><div class="metric-card"><p class="text-xs text-[var(--muted)]">أرباح الأسبوع</p><p class="mt-2 font-black">{{ number_format($this->statementSettlement->earnings_amount) }} ل.س</p></div><div class="metric-card"><p class="text-xs text-[var(--muted)]">التعديلات</p><p class="mt-2 font-black">{{ number_format($this->statementSettlement->adjustments_amount) }} ل.س</p></div><div class="metric-card"><p class="text-xs text-[var(--muted)]">المتبقي</p><p class="mt-2 font-black">{{ number_format($this->statementSettlement->remaining_amount) }} ل.س</p></div></div>
        </section>
    @endif
</div>
