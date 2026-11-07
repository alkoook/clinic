<div class="mx-auto max-w-5xl">
    @error('schedule')<div class="flash-message flash-error mb-5" role="alert">{{ $message }}</div>@enderror
    <header class="page-header"><div><p class="page-kicker">{{ $appointment ? 'تغيير مع الاحتفاظ بالسجل' : 'موعد جديد' }}</p><h1>{{ $appointment ? 'إعادة جدولة الموعد' : 'حجز موعد' }}</h1><p>سيجري التحقق آلياً من توفر المعالج والغرفة وعدم وجود تعارض.</p></div><a class="btn btn-secondary" href="{{ route('appointments.index') }}" wire:navigate>رجوع</a></header>
    <form class="app-card space-y-7 p-5 sm:p-7" wire:submit="save">
        @if ($appointment)<div class="rounded-2xl border border-[var(--line)] bg-[var(--warning-soft)] p-4 text-sm font-bold text-[var(--warning)]">لن يتغير الموعد القديم؛ سيُعلّم بأنه أُعيدت جدولته ويُنشأ موعد جديد مرتبط به.</div>@endif
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <label><span class="form-label">الطبيب / المعالج *</span><select class="form-select" wire:model.live="practitionerId"><option value="">اختر</option>@foreach ($this->practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>@endforeach</select>@error('practitionerId')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">الغرفة *</span><select class="form-select" wire:model.live="roomId"><option value="">اختر</option>@foreach ($this->rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select>@error('roomId')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">نوع العلاج *</span><select class="form-select" wire:model.live="treatmentTypeId"><option value="">اختر</option>@foreach ($this->treatmentTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}{{ $type->is_group ? ' — جماعي' : '' }}</option>@endforeach</select>@error('treatmentTypeId')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">التاريخ *</span><input class="form-input" type="date" wire:model.live="date">@error('date')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">وقت البدء *</span><input class="form-input" type="time" wire:model="startTime">@error('startTime')<span class="form-error">{{ $message }}</span>@enderror</label>
            <label><span class="form-label">المدة بالدقائق *</span><input class="form-input" type="number" inputmode="numeric" min="5" max="720" step="5" wire:model.blur="durationMinutes">@error('durationMinutes')<span class="form-error">{{ $message }}</span>@enderror</label>
        </section>
        <section id="available-periods" class="rounded-2xl bg-[var(--surface-soft)] p-4" aria-label="الأوقات المتاحة">
            <h2 class="font-semibold">المتاح في هذا اليوم</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">الفترات تكفي لمدة الموعد المحددة، بعد استبعاد الحجوزات واستثناءات الدوام للمعالج والغرفة.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse($this->availablePeriods as $period)
                    <button type="button" class="btn btn-secondary" wire:click="$set('startTime', '{{ $period['start']->format('H:i') }}')">{{ $period['start']->translatedFormat('h:i a') }} — {{ $period['end']->translatedFormat('h:i a') }}</button>
                @empty<p class="text-sm">{{ $practitionerId && $roomId ? 'لا توجد فترة مناسبة في هذا التاريخ. اختر يومًا أو غرفة أخرى.' : 'اختر المعالج والغرفة والتاريخ لعرض الفترات المتاحة.' }}</p>@endforelse
            </div>
        </section>
        <section class="border-t border-[var(--line)] pt-6">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 class="font-black text-[var(--ink)]">المرضى</h2><p class="mt-1 text-xs text-[var(--muted)]">{{ count($patientIds) }} محدد · {{ $kind === 'group' ? 'موعد جماعي' : 'موعد فردي' }}</p></div><span class="status-badge">{{ $kind === 'group' ? 'يسمح بأكثر من مريض' : 'مريض واحد فقط' }}</span></div>
            <label><span class="form-label">ابحث بالاسم أو رقم الملف أو الهاتف</span><input class="form-input" type="search" wire:model.live.debounce.300ms="patientSearch" placeholder="ابدأ الكتابة لعرض المرضى"></label>
            <div class="mt-4 grid max-h-72 gap-3 overflow-y-auto sm:grid-cols-2">
                @forelse ($this->patientOptions as $patientOption)
                    <label class="flex items-center gap-3 rounded-2xl border border-[var(--line)] p-4" wire:key="patient-option-{{ $patientOption->id }}"><input class="form-checkbox" type="checkbox" value="{{ $patientOption->id }}" wire:model="patientIds"><span><strong class="block text-sm text-[var(--ink)]">{{ $patientOption->full_name }}</strong><small class="text-[var(--muted)]">ملف {{ $patientOption->medical_file_number }} · {{ $patientOption->phone }}</small></span></label>
                @empty<div class="empty-state sm:col-span-2">{{ $patientSearch === '' ? 'ابحث عن مريض لاختياره.' : 'لا توجد نتائج مطابقة.' }}</div>@endforelse
            </div>
            @error('patientIds')<span class="form-error">{{ $message }}</span>@enderror @error('patientIds.*')<span class="form-error">{{ $message }}</span>@enderror
        </section>
        <label><span class="form-label">ملاحظات الموعد</span><textarea class="form-textarea" wire:model="notes"></textarea>@error('notes')<span class="form-error">{{ $message }}</span>@enderror</label>
        <div class="flex justify-end gap-3 border-t border-[var(--line)] pt-6"><a class="btn btn-secondary" href="{{ route('appointments.index') }}" wire:navigate>إلغاء</a><button class="btn btn-primary" type="submit" wire:loading.attr="disabled">{{ $appointment ? 'حفظ الموعد الجديد' : 'تأكيد الحجز' }}</button></div>
    </form>

    @if ($schedulingConflict)
        <div class="fixed inset-0 z-[100] grid place-items-center overflow-y-auto bg-slate-950/55 p-3 backdrop-blur-sm sm:p-6"
             role="presentation"
             x-data
             x-init="$nextTick(() => $refs.closeButton.focus())"
             x-on:keydown.escape.window="$wire.clearSchedulingConflict()">
            <section class="w-full max-w-2xl overflow-hidden rounded-3xl border border-[var(--line)] bg-[var(--surface)] shadow-2xl"
                     role="alertdialog" aria-modal="true" aria-labelledby="scheduling-conflict-title" aria-describedby="scheduling-conflict-description">
                <header class="flex items-start justify-between gap-4 border-b border-[var(--line)] bg-[var(--danger-soft)] p-5 sm:p-6">
                    <div class="min-w-0">
                        <p class="text-xs font-black text-[var(--danger)]">تعذر قبول الموعد</p>
                        <h2 id="scheduling-conflict-title" class="mt-1 text-xl font-black text-[var(--ink)]">راجع تفاصيل التعارض</h2>
                    </div>
                    <button x-ref="closeButton" class="icon-btn" type="button" wire:click="clearSchedulingConflict" aria-label="إغلاق النافذة">
                        <x-ui.icon name="close" class="size-5" />
                    </button>
                </header>

                <div class="space-y-5 p-5 sm:p-6">
                    <p id="scheduling-conflict-description" class="rounded-2xl border border-[var(--danger)]/20 bg-[var(--danger-soft)] p-4 text-sm font-bold leading-7 text-[var(--danger)]">
                        {{ $schedulingConflict['message'] }}
                    </p>

                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-2xl bg-[var(--surface-soft)] p-4"><dt class="form-label">المعالج</dt><dd class="mt-1 font-black text-[var(--ink)]">{{ $schedulingConflict['practitioner'] ?? 'غير محدد' }}</dd></div>
                        <div class="rounded-2xl bg-[var(--surface-soft)] p-4"><dt class="form-label">الغرفة</dt><dd class="mt-1 font-black text-[var(--ink)]">{{ $schedulingConflict['room'] ?? 'غير محددة' }}</dd></div>
                        <div class="rounded-2xl bg-[var(--surface-soft)] p-4"><dt class="form-label">الفترة المطلوبة</dt><dd class="mt-1 font-black text-[var(--ink)]" dir="ltr">{{ $schedulingConflict['requested_start'] }} – {{ $schedulingConflict['requested_end'] }}</dd></div>
                        <div class="rounded-2xl bg-[var(--surface-soft)] p-4"><dt class="form-label">دوام العيادة</dt><dd class="mt-1 font-black text-[var(--ink)]" dir="ltr">{{ $schedulingConflict['clinic_hours'] ?? 'العيادة مغلقة' }}</dd></div>
                    </dl>

                    @if ($schedulingConflict['conflict_start'])
                        <div class="rounded-2xl border border-[var(--warning)]/30 bg-[var(--warning-soft)] p-4 text-sm text-[var(--warning)]">
                            <strong>الفترة المتعارضة:</strong>
                            <span dir="ltr">{{ $schedulingConflict['conflict_start'] }} – {{ $schedulingConflict['conflict_end'] ?? 'مستمرة الآن' }}</span>
                            <p class="mt-1 text-xs">لا تُعرض أي بيانات خاصة بالمريض المرتبط بالحجز الآخر.</p>
                        </div>
                    @endif

                    <div>
                        <h3 class="font-black text-[var(--ink)]">دوام المعالج في اليوم المحدد</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($schedulingConflict['practitioner_hours'] as $period)
                                <span class="status-badge" dir="ltr">{{ $period }}</span>
                            @empty
                                <span class="text-sm text-[var(--muted)]">لا توجد فترة دوام متاحة.</span>
                            @endforelse
                        </div>
                    </div>

                    @if ($schedulingConflict['alternatives'] !== [])
                        <div>
                            <h3 class="font-black text-[var(--ink)]">بدائل متحققة لنفس المدة</h3>
                            <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                @foreach ($schedulingConflict['alternatives'] as $alternative)
                                    <button class="btn btn-secondary" type="button" wire:click="selectSuggestedTime('{{ $alternative['start'] }}')" wire:key="suggested-{{ $alternative['start'] }}">
                                        <span dir="ltr">{{ $alternative['start'] }} – {{ $alternative['end'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <footer class="flex flex-col-reverse gap-2 border-t border-[var(--line)] p-5 sm:flex-row sm:justify-end sm:p-6">
                    <button class="btn btn-secondary" type="button" wire:click="clearSchedulingConflict">إغلاق</button>
                    <button class="btn btn-primary" type="button" x-on:click="document.getElementById('available-periods')?.scrollIntoView({ behavior: 'smooth' }); $wire.clearSchedulingConflict()">عرض الفترات المتاحة</button>
                </footer>
            </section>
        </div>
    @endif
</div>
