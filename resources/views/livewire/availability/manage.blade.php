<div class="space-y-6">
    <header class="page-header"><div><p class="page-kicker">جدول مرن</p><h1>أوقات توفر {{ $practitioner->name }}</h1><p>فترات أسبوعية متكررة مع استثناءات يومية للعمل أو الغياب.</p></div>@can('practitioners.manage')<a class="btn btn-secondary" href="{{ route('practitioners.show', $practitioner) }}" wire:navigate>ملف المعالج</a>@endcan</header>
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="app-card p-5 sm:p-6">
            <h2 class="mb-4 font-black text-[var(--ink)]">الدوام الأسبوعي المتكرر</h2>
            @if($this->editable)<form class="grid gap-3 sm:grid-cols-2" wire:submit="addPeriod">
                <label class="sm:col-span-2"><span class="form-label">اليوم</span><select class="form-select" wire:model="dayOfWeek">@foreach ($this->weekdayLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label><span class="form-label">من *</span><input class="form-input" type="time" wire:model="startTime">@error('startTime')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">إلى *</span><input class="form-input" type="time" wire:model="endTime">@error('endTime')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">فعال من</span><input class="form-input" type="date" wire:model="effectiveFrom"></label>
                <label><span class="form-label">فعال حتى</span><input class="form-input" type="date" wire:model="effectiveTo">@error('effectiveTo')<span class="form-error">{{ $message }}</span>@enderror</label>
                <button class="btn btn-primary sm:col-span-2" type="submit">إضافة الفترة</button>
            </form>@endif
            <div class="mt-6 space-y-3">@forelse ($this->periods as $period)<div class="flex items-center justify-between gap-3 rounded-2xl bg-[var(--surface-soft)] p-4" wire:key="period-{{ $period->id }}"><div><p class="font-bold text-[var(--ink)]">{{ $this->weekdayLabels()[$period->day_of_week] }}</p><p class="text-xs text-[var(--muted)]" dir="ltr">{{ substr($period->start_time, 0, 5) }} — {{ substr($period->end_time, 0, 5) }}</p></div>@if($this->editable)<button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف فترة الدوام؟', description: 'لن تُستخدم هذه الفترة عند التحقق من المواعيد الجديدة.', confirmLabel: 'حذف الفترة', action: () => $wire.removePeriod({{ $period->id }}) })">حذف</button>@endif</div>@empty<div class="empty-state">لم تُضف فترات دوام.</div>@endforelse</div>
        </section>
        <section class="app-card p-5 sm:p-6">
            <h2 class="mb-4 font-black text-[var(--ink)]">استثناءات التوفر</h2>
            @if($this->editable)<form class="grid gap-3 sm:grid-cols-2" wire:submit="addException">
                <label><span class="form-label">التاريخ *</span><input class="form-input" type="date" wire:model="exceptionDate">@error('exceptionDate')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">النوع *</span><select class="form-select" wire:model.live="exceptionType">@foreach (App\Enums\AvailabilityExceptionType::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></label>
                @if ($exceptionType === App\Enums\AvailabilityExceptionType::Available->value)
                    <label><span class="form-label">من *</span><input class="form-input" type="time" wire:model="exceptionStartTime">@error('exceptionStartTime')<span class="form-error">{{ $message }}</span>@enderror</label>
                    <label><span class="form-label">إلى *</span><input class="form-input" type="time" wire:model="exceptionEndTime">@error('exceptionEndTime')<span class="form-error">{{ $message }}</span>@enderror</label>
                @endif
                <label class="sm:col-span-2"><span class="form-label">السبب</span><input class="form-input" wire:model="exceptionReason"></label>
                <button class="btn btn-primary sm:col-span-2" type="submit">حفظ الاستثناء</button>
            </form>@endif
            <div class="mt-6 space-y-3">@forelse ($this->exceptions as $exception)<div class="flex items-center justify-between gap-3 rounded-2xl border border-[var(--line)] p-4" wire:key="exception-{{ $exception->id }}"><div><p class="font-bold text-[var(--ink)]">{{ $exception->date->format('Y/m/d') }} · {{ $exception->type->label() }}</p><p class="text-xs text-[var(--muted)]">{{ $exception->reason ?: 'دون سبب' }} @if($exception->start_time) · {{ substr($exception->start_time, 0, 5) }} — {{ substr($exception->end_time, 0, 5) }} @endif</p></div>@if($this->editable)<button class="btn btn-danger" type="button" x-on:click="$dispatch('confirm-action', { title: 'حذف الاستثناء؟', description: 'سيعود جدول الإتاحة إلى قواعد الدوام المعتادة لهذا التاريخ.', confirmLabel: 'حذف الاستثناء', action: () => $wire.removeException({{ $exception->id }}) })">حذف</button>@endif</div>@empty<div class="empty-state">لا توجد استثناءات حديثة.</div>@endforelse</div>
        </section>
    </div>
</div>
