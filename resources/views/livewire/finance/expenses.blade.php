<div class="space-y-6">
    <header class="page-header">
        <div>
            <p class="page-kicker">مصروفات التشغيل</p>
            <h1>المصاريف</h1>
            <p>سجّل المصروف مرة واحدة مع حركته النقدية وحدّد أثره على صافي الأسبوع.</p>
        </div>
    </header>

    <section class="app-card grid gap-4 p-4 md:grid-cols-3">
        <label><span class="form-label">من تاريخ</span><input class="form-input" type="date" wire:model.live="dateFrom"></label>
        <label><span class="form-label">إلى تاريخ</span><input class="form-input" type="date" wire:model.live="dateTo"></label>
        <label>
            <span class="form-label">تصنيف المصروف</span>
            <select class="form-select" wire:model.live="filterCategoryId">
                <option value="">كل التصنيفات</option>
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
    </section>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
        <section class="space-y-4">
            <div class="table-shell">
                <table>
                    <thead><tr><th>التاريخ</th><th>التصنيف</th><th>الوصف</th><th>الصندوق</th><th>المبلغ</th><th>صافي الأسبوع</th><th>المسجّل</th></tr></thead>
                    <tbody>
                        @forelse ($this->expenses as $expense)
                            <tr wire:key="expense-{{ $expense->id }}">
                                <td class="whitespace-nowrap">{{ $expense->occurred_at->format('Y/m/d H:i') }}</td>
                                <td><span class="status-badge">{{ $expense->category->name }}</span></td>
                                <td class="max-w-xs"><p class="line-clamp-2">{{ $expense->description ?: '—' }}</p></td>
                                <td>{{ $expense->cashbox->name }}</td>
                                <td class="whitespace-nowrap font-black text-[var(--danger)]">{{ number_format($expense->amount) }} ل.س</td>
                                <td><span class="status-badge {{ $expense->affects_weekly_net ? 'status-warning' : '' }}">{{ $expense->affects_weekly_net ? 'محتسب' : 'غير محتسب' }}</span></td>
                                <td>{{ $expense->creator?->name ?: 'النظام' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">لا توجد مصاريف ضمن الفترة المحددة.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $this->expenses->links() }}
        </section>

        <aside class="space-y-5">
            <form class="app-card space-y-4 p-5" wire:submit="record">
                <div><p class="page-kicker">حركة نقدية جديدة</p><h2 class="mt-1 text-lg font-black text-[var(--ink)]">تسجيل مصروف</h2></div>
                <label>
                    <span class="form-label">التصنيف *</span>
                    <select class="form-select" wire:model="expenseCategoryId">
                        <option value="">اختر التصنيف</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('expenseCategoryId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span class="form-label">الصندوق *</span>
                    <select class="form-select" wire:model="cashboxId">
                        <option value="">اختر الصندوق</option>
                        @foreach ($this->cashboxes as $cashbox)
                            <option value="{{ $cashbox->id }}">{{ $cashbox->name }} — {{ $cashbox->type->label() }}</option>
                        @endforeach
                    </select>
                    @error('cashboxId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                    <label>
                        <span class="form-label">المبلغ (ل.س) *</span>
                        <input class="form-input" type="number" min="1" step="1" inputmode="numeric" wire:model="amount" placeholder="0">
                        @error('amount')<span class="form-error">{{ $message }}</span>@enderror
                    </label>
                    <label>
                        <span class="form-label">وقت المصروف *</span>
                        <input class="form-input" type="datetime-local" wire:model="occurredAt">
                        @error('occurredAt')<span class="form-error">{{ $message }}</span>@enderror
                    </label>
                </div>
                <label>
                    <span class="form-label">الوصف</span>
                    <textarea class="form-textarea" wire:model="description" placeholder="سبب المصروف أو رقم الفاتورة"></textarea>
                    @error('description')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <label class="flex items-start gap-3 rounded-2xl bg-[var(--surface-soft)] p-4">
                    <input class="form-checkbox mt-0.5" type="checkbox" wire:model="affectsWeeklyNet">
                    <span><span class="block text-sm font-bold text-[var(--ink)]">يؤثر في صافي الأسبوع</span><span class="mt-1 block text-xs leading-5 text-[var(--muted)]">ألغِ التحديد فقط للمبالغ التي لا تدخل في نتيجة التشغيل الأسبوعية.</span></span>
                </label>
                <button class="btn btn-primary w-full" type="submit" wire:loading.attr="disabled" wire:target="record">
                    <span wire:loading.remove wire:target="record">تسجيل المصروف</span><span wire:loading wire:target="record">جارٍ التسجيل…</span>
                </button>
            </form>

            @can('master_data.manage')
                <form class="app-card space-y-3 p-5" wire:submit="addCategory">
                    <div><p class="page-kicker">بيانات مساعدة</p><h2 class="mt-1 font-black text-[var(--ink)]">تصنيف مصروف جديد</h2></div>
                    <label>
                        <span class="form-label">اسم التصنيف</span>
                        <input class="form-input" wire:model="newCategoryName" placeholder="مثال: صيانة">
                        @error('newCategoryName')<span class="form-error">{{ $message }}</span>@enderror
                    </label>
                    <button class="btn btn-secondary w-full" type="submit" wire:loading.attr="disabled" wire:target="addCategory">إضافة التصنيف</button>
                </form>
            @endcan
        </aside>
    </div>
</div>
