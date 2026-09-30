<div class="space-y-6">
    <header class="page-header">
        <div>
            <p class="page-kicker">دفتر النقد</p>
            <h1>الصناديق والحركات</h1>
            <p>الأرصدة محسوبة من سجل الحركات غير القابل للتعديل، والتحويل يولّد حركتين مترابطتين.</p>
        </div>
    </header>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-label="أرصدة الصناديق">
        @forelse ($this->cashboxes as $cashbox)
            <article class="metric-card" wire:key="cashbox-balance-{{ $cashbox->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold text-[var(--muted)]">{{ $cashbox->type->label() }}</p>
                        <h2 class="mt-1 text-lg font-black text-[var(--ink)]">{{ $cashbox->name }}</h2>
                    </div>
                    <span class="status-badge {{ $cashbox->is_active ? 'status-success' : 'status-danger' }}">{{ $cashbox->is_active ? 'نشط' : 'متوقف' }}</span>
                </div>
                <p class="mt-5 text-3xl font-black {{ (int) $cashbox->balance < 0 ? 'text-[var(--danger)]' : 'text-[var(--brand-strong)]' }}" dir="ltr">{{ number_format((int) $cashbox->balance) }} <span class="text-sm">ل.س</span></p>
                <p class="mt-2 text-xs text-[var(--muted)]">الرصيد الحالي وفق جميع الحركات المسجلة</p>
            </article>
        @empty
            <div class="empty-state sm:col-span-2">لم تُهيأ الصناديق بعد.</div>
        @endforelse
    </section>

    <section class="grid items-start gap-6 lg:grid-cols-2">
        <form class="app-card space-y-4 p-5 sm:p-6" wire:submit="recordManual">
            <div><p class="page-kicker">إدخال يدوي</p><h2 class="mt-1 text-lg font-black text-[var(--ink)]">إيداع أو سحب</h2><p class="mt-2 text-xs leading-6 text-[var(--muted)]">استخدمه فقط للحركة الفعلية التي لا تتبع دفعة أو مصروفاً مسجلاً في النظام.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="form-label">الصندوق *</span>
                    <select class="form-select" wire:model="manualCashboxId">
                        <option value="">اختر الصندوق</option>
                        @foreach ($this->cashboxes->where('is_active', true) as $cashbox)
                            <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                        @endforeach
                    </select>
                    @error('manualCashboxId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span class="form-label">نوع الحركة *</span>
                    <select class="form-select" wire:model="manualDirection">
                        <option value="in">إيداع نقدي</option>
                        <option value="out">سحب نقدي</option>
                    </select>
                    @error('manualDirection')<span class="form-error">{{ $message }}</span>@enderror
                </label>
            </div>
            <label>
                <span class="form-label">المبلغ (ل.س) *</span>
                <input class="form-input" type="number" min="1" step="1" inputmode="numeric" wire:model="manualAmount" placeholder="0">
                @error('manualAmount')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <label>
                <span class="form-label">سبب الحركة (اختياري)</span>
                <textarea class="form-textarea" wire:model="manualDescription" placeholder="ملاحظة اختيارية للمراجعة"></textarea>
                @error('manualDescription')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <button class="btn btn-primary w-full" type="submit" wire:loading.attr="disabled" wire:target="recordManual"><span wire:loading.remove wire:target="recordManual">تسجيل الحركة</span><span wire:loading wire:target="recordManual">جارٍ التسجيل…</span></button>
        </form>

        <form class="app-card space-y-4 p-5 sm:p-6" wire:submit="transfer">
            <div><p class="page-kicker">بين الصناديق</p><h2 class="mt-1 text-lg font-black text-[var(--ink)]">تحويل نقدي</h2><p class="mt-2 text-xs leading-6 text-[var(--muted)]">يُسجّل المبلغ كخروج من المصدر ودخول إلى الوجهة بنفس المرجع.</p></div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="form-label">من صندوق *</span>
                    <select class="form-select" wire:model="fromCashboxId">
                        <option value="">اختر المصدر</option>
                        @foreach ($this->cashboxes->where('is_active', true) as $cashbox)
                            <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                        @endforeach
                    </select>
                    @error('fromCashboxId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span class="form-label">إلى صندوق *</span>
                    <select class="form-select" wire:model="toCashboxId">
                        <option value="">اختر الوجهة</option>
                        @foreach ($this->cashboxes->where('is_active', true) as $cashbox)
                            <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                        @endforeach
                    </select>
                    @error('toCashboxId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
            </div>
            <label>
                <span class="form-label">مبلغ التحويل (ل.س) *</span>
                <input class="form-input" type="number" min="1" step="1" inputmode="numeric" wire:model="transferAmount" placeholder="0">
                @error('transferAmount')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <label>
                <span class="form-label">سبب التحويل (اختياري)</span>
                <textarea class="form-textarea" wire:model="transferReason" placeholder="مثال: تحويل فائض الفترة"></textarea>
                @error('transferReason')<span class="form-error">{{ $message }}</span>@enderror
            </label>
            <button class="btn btn-secondary w-full" type="submit" wire:loading.attr="disabled" wire:target="transfer"><span wire:loading.remove wire:target="transfer">تنفيذ التحويل</span><span wire:loading wire:target="transfer">جارٍ التحويل…</span></button>
        </form>
    </section>

    <section class="space-y-4">
        <div class="app-card flex flex-wrap items-end justify-between gap-4 p-4">
            <div><p class="page-kicker">سجل رقابي</p><h2 class="mt-1 font-black text-[var(--ink)]">آخر الحركات</h2></div>
            <label class="w-full sm:w-72">
                <span class="form-label">الصندوق</span>
                <select class="form-select" wire:model.live="filterCashboxId">
                    <option value="">كل الصناديق</option>
                    @foreach ($this->cashboxes as $cashbox)
                        <option value="{{ $cashbox->id }}">{{ $cashbox->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="table-shell">
            <table>
                <thead><tr><th>الوقت</th><th>الصندوق</th><th>الحركة</th><th>الاتجاه</th><th>المبلغ</th><th>الوصف</th><th>المسجّل</th></tr></thead>
                <tbody>
                    @forelse ($this->transactions as $transaction)
                        <tr wire:key="cashbox-transaction-{{ $transaction->id }}">
                            <td class="whitespace-nowrap">{{ $transaction->occurred_at->format('Y/m/d H:i') }}</td>
                            <td class="font-bold text-[var(--ink)]">{{ $transaction->cashbox->name }}</td>
                            <td>{{ $transaction->type->label() }}</td>
                            <td><span class="status-badge {{ $transaction->direction === App\Enums\TransactionDirection::In ? 'status-success' : 'status-danger' }}">{{ $transaction->direction === App\Enums\TransactionDirection::In ? 'داخل' : 'خارج' }}</span></td>
                            <td class="whitespace-nowrap font-black {{ $transaction->direction === App\Enums\TransactionDirection::In ? 'text-[var(--success)]' : 'text-[var(--danger)]' }}" dir="ltr">{{ $transaction->direction === App\Enums\TransactionDirection::In ? '+' : '−' }}{{ number_format($transaction->amount) }} ل.س</td>
                            <td class="max-w-sm"><p class="line-clamp-2">{{ App\Support\PresentationLabels::value('description', $transaction->description) }}</p></td>
                            <td>{{ $transaction->creator?->name ?: 'النظام' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state">لا توجد حركات مسجلة لهذا الصندوق.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $this->transactions->links() }}
    </section>
</div>
