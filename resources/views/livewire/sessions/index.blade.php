<div class="space-y-6">
    <header class="page-header"><div><p class="page-kicker">الخدمة الفعلية</p><h1>الجلسات العلاجية</h1><p>الجلسة كيان مستقل عن الموعد، ويُثبت أثرها المالي عند الدفع النقدي.</p></div></header>

    @can('sessions.manage')
        <section class="app-card p-5 sm:p-6">
            <div class="mb-4"><h2 class="font-black text-[var(--ink)]">مواعيد جاهزة لبدء جلسة</h2><p class="mt-1 text-xs text-[var(--muted)]">تظهر مواعيد الأمس واليوم والغد التي لم تُنشأ لها جلسة.</p></div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->readyAppointments as $appointment)
                    <article class="rounded-2xl border border-[var(--line)] p-4" wire:key="ready-appointment-{{ $appointment->id }}">
                        <div class="flex justify-between gap-3"><time class="font-black text-[var(--brand-strong)]">{{ $appointment->starts_at->format('Y/m/d H:i') }}</time><span class="status-badge">{{ $appointment->status->label() }}</span></div>
                        <p class="mt-3 font-bold text-[var(--ink)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') }}</p>
                        <p class="mt-1 text-xs text-[var(--muted)]">{{ $appointment->practitioner?->name }} · {{ $appointment->treatmentType?->name }}</p>
                        <button class="btn btn-primary mt-4 w-full" type="button" wire:click="prepareSession({{ $appointment->id }})">تحديد الأسعار وبدء الجلسة</button>
                    </article>
                @empty<div class="empty-state md:col-span-2 xl:col-span-3">لا توجد مواعيد جاهزة الآن.</div>@endforelse
            </div>
        </section>

        @if ($startingAppointmentId)
            @php($selectedAppointment = $this->readyAppointments->firstWhere('id', $startingAppointmentId))
            <form class="app-card border-2 border-[var(--brand)] p-5 sm:p-6" wire:submit="createSession">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><p class="page-kicker">تثبيت بيانات الجلسة</p><h2 class="mt-1 font-black text-[var(--ink)]">{{ $selectedAppointment?->treatmentType?->name }} · {{ $selectedAppointment?->practitioner?->name }}</h2></div><button class="btn btn-secondary" type="button" wire:click="$set('startingAppointmentId', null)">إلغاء</button></div>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($selectedAppointment?->participants ?? [] as $participant)
                        <fieldset class="rounded-2xl bg-[var(--surface-soft)] p-4" wire:key="session-price-{{ $participant->id }}">
                            <legend class="mb-3 font-black text-[var(--ink)]">{{ $participant->patient?->full_name }}</legend>
                            @if (($selectedAppointment?->participants->count() ?? 0) > 1)
                                <label class="mb-3 flex items-center gap-2"><input class="form-checkbox" type="checkbox" value="{{ $participant->id }}" wire:model.live="attendingParticipantIds"><span class="font-bold text-[var(--ink-soft)]">حضر الجلسة</span></label>
                            @endif
                            @if (in_array($participant->id, $attendingParticipantIds, true))
                                <div class="grid grid-cols-2 gap-3"><label><span class="form-label">السعر الأصلي *</span><input class="form-input" type="number" min="1" wire:model="prices.{{ $participant->id }}.original_price">@error("prices.$participant->id.original_price")<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">الخصم</span><input class="form-input" type="number" min="0" wire:model="prices.{{ $participant->id }}.discount_amount">@error("prices.$participant->id.discount_amount")<span class="form-error">{{ $message }}</span>@enderror</label></div>
                            @else
                                <p class="text-sm text-[var(--muted)]">سيُسجّل المريض كمتغيّب ولن ينشأ له سجل مالي.</p>
                            @endif
                        </fieldset>
                    @endforeach
                </div>
                @error('attendingParticipantIds')<p class="form-error mt-3">{{ $message }}</p>@enderror
                @error('operation')<p class="form-error mt-3">{{ $message }}</p>@enderror
                <button class="btn btn-primary mt-5" type="submit" wire:loading.attr="disabled">إنشاء الجلسة</button>
            </form>
        @endif
    @endcan

    <section class="app-card grid gap-3 p-4 sm:grid-cols-3">
        <label><span class="form-label">من</span><input class="form-input" type="date" wire:model.live="dateFrom"></label>
        <label><span class="form-label">إلى</span><input class="form-input" type="date" wire:model.live="dateTo"></label>
        <label><span class="form-label">الحالة</span><select class="form-select" wire:model.live="status"><option value="">كل الحالات</option>@foreach (App\Enums\TreatmentSessionStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></label>
    </section>

    <div class="table-shell"><table class="mobile-record-table"><thead><tr><th>الجلسة</th><th>التاريخ</th><th>المرضى</th><th>المعالج والعلاج</th><th>المبلغ</th>@if ($this->canViewPractitionerShare)<th>حصة المعالج</th>@endif<th>الحالة</th><th></th></tr></thead><tbody>
        @forelse ($this->sessions as $session)
            <tr wire:key="session-{{ $session->id }}"><td data-label="الجلسة" class="font-black text-[var(--brand-strong)]">#{{ $session->id }}</td><td data-label="وقت البدء الفعلي">{{ $session->started_at->format('Y/m/d H:i') }}</td><td data-label="المرضى">{{ $session->participants->pluck('patient.full_name')->filter()->join('، ') }}</td><td data-label="المعالج والعلاج"><p>{{ $session->practitioner?->name }}</p><p class="text-xs text-[var(--muted)]">{{ $session->treatmentType?->name }} · {{ $session->room?->name }}</p></td><td data-label="المبلغ" class="font-bold text-[var(--ink)]">{{ number_format((int) $session->participants_sum_final_amount) }}</td>@if ($this->canViewPractitionerShare)<td data-label="حصة المعالج">{{ number_format((int) $session->participants_sum_practitioner_share) }}</td>@endif<td data-label="الحالة"><span class="status-badge">{{ $session->status->label() }}</span></td><td data-label="الإجراء"><a class="btn btn-secondary" href="{{ route('sessions.show', $session) }}" wire:navigate>فتح</a></td></tr>
        @empty<tr><td colspan="{{ $this->canViewPractitionerShare ? 8 : 7 }}"><div class="empty-state">لا توجد جلسات ضمن الفترة.</div></td></tr>@endforelse
    </tbody></table></div>
    {{ $this->sessions->links() }}
</div>
