<div class="space-y-6">
    <header class="page-header">
        <div>
            <p class="page-kicker">قواعد احتساب المستحقات</p>
            <h1>الاتفاقات المالية</h1>
            <p>حدّد نسبة واحدة لكل معالج ضمن فترة زمنية؛ وتبقى نسب الدفعات السابقة محفوظة كسجل تاريخي ثابت.</p>
        </div>
        @if ($editingId)
            <button class="btn btn-secondary" type="button" wire:click="cancelEditing">إلغاء التعديل</button>
        @endif
    </header>

    <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(20rem,25rem)_minmax(0,1fr)]">
        <form class="app-card space-y-5 p-5 sm:p-6" wire:submit="save">
            <div>
                <p class="page-kicker">{{ $editingId ? 'تعديل الاتفاق' : 'اتفاق جديد' }}</p>
                <h2 class="mt-1 text-lg font-black text-[var(--ink)]">{{ $editingId ? 'تحديث شروط الاتفاق' : 'إضافة شروط مالية' }}</h2>
                <p class="mt-2 text-xs leading-6 text-[var(--muted)]">لا يؤثر تعديل الاتفاق على النسب والمبالغ المحفوظة داخل الجلسات السابقة.</p>
            </div>

            <label>
                <span class="form-label">الطبيب / المعالج *</span>
                <select class="form-select" wire:model="practitionerId" @error('practitionerId') aria-invalid="true" @enderror>
                    <option value="">اختر المعالج</option>
                    @foreach ($this->practitioners as $practitioner)
                        <option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>
                    @endforeach
                </select>
                @error('practitionerId')<span class="form-error">{{ $message }}</span>@enderror
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="form-label">نسبة المعالج % *</span>
                    <input class="form-input" type="number" min="0" max="100" step="0.01" inputmode="decimal" wire:model="practitionerPercentage" placeholder="مثال: 40">
                    @error('practitionerPercentage')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span class="form-label">ساري من *</span>
                    <input class="form-input" type="date" wire:model="effectiveFrom">
                    @error('effectiveFrom')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                <label>
                    <span class="form-label">ساري حتى</span>
                    <input class="form-input" type="date" wire:model="effectiveTo">
                    @error('effectiveTo')<span class="form-error">{{ $message }}</span>@enderror
                </label>
            </div>

            <label>
                <span class="form-label">ملاحظات الاتفاق</span>
                <textarea class="form-textarea" wire:model="notes" placeholder="تفاصيل داخلية اختيارية"></textarea>
                @error('notes')<span class="form-error">{{ $message }}</span>@enderror
            </label>

            <label class="flex items-center gap-3 rounded-2xl bg-[var(--surface-soft)] p-4">
                <input class="form-checkbox" type="checkbox" wire:model="isActive">
                <span>
                    <span class="block text-sm font-bold text-[var(--ink)]">الاتفاق فعّال</span>
                    <span class="mt-1 block text-xs text-[var(--muted)]">تُستخدم النسبة عند إنشاء جلسة ضمن فترة السريان.</span>
                </span>
            </label>

            <div class="flex flex-wrap justify-end gap-2 border-t border-[var(--line)] pt-5">
                @if ($editingId)
                    <button class="btn btn-secondary" type="button" wire:click="cancelEditing">إلغاء</button>
                @endif
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'حفظ التعديلات' : 'إضافة الاتفاق' }}</span>
                    <span wire:loading wire:target="save">جارٍ الحفظ…</span>
                </button>
            </div>
        </form>

        <section class="space-y-4">
            <div class="app-card p-4">
                <div class="grid gap-3 sm:grid-cols-2"><label>
                    <span class="form-label">تصفية حسب المعالج</span>
                    <select class="form-select" wire:model.live="filterPractitionerId">
                        <option value="">كل المعالجين</option>
                        @foreach ($this->practitioners as $practitioner)
                            <option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>
                        @endforeach
                    </select>
                </label><label><span class="form-label">حالة الاتفاق</span><select class="form-select" wire:model.live="filterStatus"><option value="active">الفعالة</option><option value="ended">المنتهية والمتوقفة</option><option value="archived">المؤرشفة</option><option value="all">الكل</option></select></label></div>
            </div>

            <div class="table-shell">
                <table>
                    <thead>
                        <tr><th>المعالج</th><th>حصة المعالج</th><th>حصة العيادة</th><th>فترة السريان</th><th>الحالة</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($this->agreements as $agreement)
                            <tr wire:key="agreement-{{ $agreement->id }}">
                                <td>
                                    <p class="font-bold text-[var(--ink)]">{{ $agreement->practitioner->name }}</p>
                                    <p class="text-xs text-[var(--muted)]">أضيف بواسطة {{ $agreement->creator?->name ?: 'النظام' }}</p>
                                </td>
                                <td><span class="text-lg font-black text-[var(--brand-strong)]">{{ number_format((float) $agreement->practitioner_percentage, 2) }}%</span></td>
                                <td><span class="text-lg font-bold text-[var(--ink-soft)]">{{ number_format(100 - (float) $agreement->practitioner_percentage, 2) }}%</span></td>
                                <td>
                                    <p>{{ $agreement->effective_from->format('Y/m/d') }}</p>
                                    <p class="text-xs text-[var(--muted)]">حتى {{ $agreement->effective_to?->format('Y/m/d') ?: 'مفتوح' }}</p>
                                </td>
                                <td><span class="status-badge {{ $agreement->is_active ? 'status-success' : 'status-danger' }}">{{ $agreement->is_active ? 'فعّال' : 'متوقف' }}</span></td>
                                <td><div class="flex flex-wrap gap-2">@unless($agreement->trashed())<button class="btn btn-secondary" type="button" wire:click="edit({{ $agreement->id }})">تعديل</button><button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'أرشفة الاتفاق المالي؟', description: 'سيختفي من الاتفاقات الفعالة مع بقاء الدفعات واللقطات التاريخية سليمة.', confirmLabel: 'أرشفة الاتفاق', action: () => $wire.delete({{ $agreement->id }}) })">حذف الاتفاق</button>@else<span class="status-badge">مؤرشف</span>@endunless</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="empty-state">لا توجد اتفاقات مالية مطابقة.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $this->agreements->links() }}
        </section>
    </div>
</div>
