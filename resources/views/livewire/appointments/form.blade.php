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
            <label><span class="form-label">المدة بالدقائق *</span><input class="form-input" type="number" min="5" max="720" step="5" wire:model.live="durationMinutes">@error('durationMinutes')<span class="form-error">{{ $message }}</span>@enderror</label>
        </section>
        <section class="rounded-2xl bg-[var(--surface-soft)] p-4" aria-label="الأوقات المتاحة">
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
</div>
