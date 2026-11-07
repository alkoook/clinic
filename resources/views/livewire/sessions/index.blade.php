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

    <section class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3" aria-label="قائمة الجلسات">
        @forelse ($this->sessions as $session)
            @php($isFullyPaid = $session->participants->isNotEmpty() && $session->participants->every(fn ($participant) => $participant->payment_status === App\Enums\ParticipantPaymentStatus::Paid))
            <article class="app-card overflow-hidden" wire:key="session-{{ $session->id }}">
                <div class="flex items-start justify-between gap-3 border-b border-[var(--line)] p-4 sm:p-5">
                    <div><p class="page-kicker">جلسة #{{ $session->id }}</p><h2 class="mt-1 text-lg font-black text-[var(--ink)]">{{ $session->participants->pluck('patient_name')->filter()->join('، ') }}</h2><time class="mt-1 block text-xs text-[var(--muted)]">{{ App\Support\ArabicFormat::dateTime($session->started_at) }}</time></div>
                    <span class="status-badge {{ $session->status === App\Enums\TreatmentSessionStatus::Completed ? 'status-success' : '' }}">{{ $session->status->label() }}</span>
                </div>
                <div class="space-y-4 p-4 sm:p-5">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div><dt class="form-label">المعالج</dt><dd class="mt-1 font-bold text-[var(--ink)]">{{ $session->practitioner?->name }}</dd></div>
                        <div><dt class="form-label">العلاج</dt><dd class="mt-1 font-bold text-[var(--ink)]">{{ $session->treatmentType?->name }}</dd></div>
                        <div><dt class="form-label">الغرفة</dt><dd class="mt-1 font-bold text-[var(--ink)]">{{ $session->room?->name }}</dd></div>
                        <div><dt class="form-label">الدفع</dt><dd class="mt-1"><span class="status-badge {{ $isFullyPaid ? 'status-success' : 'status-warning' }}">{{ $isFullyPaid ? 'مكتمل' : 'غير مكتمل' }}</span></dd></div>
                    </dl>
                    <div class="grid grid-cols-2 gap-3 rounded-2xl bg-[var(--surface-soft)] p-4 text-sm">
                        <div><p class="form-label">إجمالي الجلسة</p><p class="mt-1 font-black text-[var(--ink)]">{{ App\Support\ArabicFormat::money((int) $session->participants_sum_final_amount) }}</p></div>
                        @if ($this->canViewPractitionerShare)<div><p class="form-label">حصة المعالج</p><p class="mt-1 font-black text-[var(--ink)]">{{ App\Support\ArabicFormat::money((int) $session->participants_sum_practitioner_share) }}</p></div>@endif
                    </div>
                    <a class="btn btn-primary w-full" href="{{ route('sessions.show', $session) }}" wire:navigate>فتح تفاصيل الجلسة</a>
                </div>
            </article>
        @empty
            <div class="empty-state lg:col-span-2 2xl:col-span-3">لا توجد جلسات ضمن الفترة.</div>
        @endforelse
    </section>
    {{ $this->sessions->links() }}
</div>
