<div class="space-y-6" wire:poll.30s.visible>
    @error('operation')<div class="flash-message flash-error">{{ $message }}</div>@enderror
    <header class="page-header"><div><p class="page-kicker">جلسة #{{ $treatmentSession->id }}</p><h1>{{ $treatmentSession->treatment_type_name }}</h1><p>{{ $treatmentSession->practitioner_name }} · {{ $treatmentSession->room_name }} · {{ App\Support\ArabicFormat::dateTime($treatmentSession->started_at) }}</p></div><div class="flex flex-wrap gap-2"><span class="status-badge">{{ $treatmentSession->status->label() }}</span><a class="btn btn-secondary" href="{{ route('sessions.index') }}" wire:navigate>الجلسات</a></div></header>
    <section class="app-card grid gap-4 p-5 sm:grid-cols-3">
        <div><p class="form-label">موعد الحجز</p><time>{{ App\Support\ArabicFormat::dateTime($treatmentSession->appointment->starts_at) }}</time></div>
        <div><p class="form-label">بدء الجلسة فعليًا</p><time>{{ App\Support\ArabicFormat::dateTime($treatmentSession->started_at) }}</time></div>
        <div><p class="form-label">مدة الجلسة الفعلية</p><span>{{ (int) $treatmentSession->started_at->diffInMinutes($treatmentSession->ended_at ?? now()) }} دقيقة {{ $treatmentSession->ended_at ? '' : '— قيد التنفيذ' }}</span></div>
    </section>
    @php
        $nextAppointmentWarning = $this->nextAppointmentWarning;
    @endphp
    @if ($nextAppointmentWarning)
        <aside class="rounded-2xl border border-[var(--warning)] bg-[var(--warning-soft)] p-4 text-[var(--warning)]" role="alert">
            <p class="font-black">{{ $nextAppointmentWarning->starts_at->isFuture() ? 'اقترب موعد الحجز التالي' : 'حان موعد الحجز التالي' }}</p>
            <p class="mt-1 text-sm font-bold">
                {{ App\Support\ArabicFormat::dateTime($nextAppointmentWarning->starts_at) }}
                · {{ $nextAppointmentWarning->participants->pluck('patient.full_name')->filter()->join('، ') }}
                · {{ $nextAppointmentWarning->practitioner?->name ?? $treatmentSession->practitioner_name }}
                · {{ $nextAppointmentWarning->room?->name ?? $treatmentSession->room_name }}
            </p>
            <p class="mt-1 text-xs font-bold">الجلسة الحالية ما زالت قيد التنفيذ ولن تُنهى تلقائيًا. يرجى تنسيق توفر المعالج والغرفة للموعد التالي.</p>
        </aside>
    @endif
    <section class="space-y-4">
        @foreach ($treatmentSession->participants as $participant)
            <article class="app-card p-5 sm:p-6" wire:key="participant-{{ $participant->id }}">
                <div class="mb-5 flex flex-wrap items-start justify-between gap-3"><div><a class="text-lg font-black text-[var(--ink)] hover:text-[var(--brand-strong)]" href="{{ route('patients.show', $participant->patient) }}" wire:navigate>{{ $participant->patient_name }}</a><p class="mt-1 text-xs text-[var(--muted)]">ملف {{ $participant->patient?->medical_file_number }} · {{ $participant->patient_source->label() }}</p></div><span class="status-badge {{ $participant->payment_status === App\Enums\ParticipantPaymentStatus::Paid ? 'status-success' : '' }}">{{ $participant->payment_status->label() }}</span></div>

                @if ($participant->payment)
                    @php
                        $paymentDetails = [
                            ['السعر الأصلي', $participant->original_price],
                            ['الخصم', $participant->discount_amount],
                            ['المدفوع', $participant->payment->amount],
                        ];

                        if ($this->canViewPractitionerShare) {
                            $paymentDetails[] = ['نسبة المعالج', $participant->practitioner_percentage.'%'];
                            $paymentDetails[] = ['حصة المعالج', $participant->practitioner_share];
                        }
                    @endphp
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach ($paymentDetails as [$label, $value])
                            <div class="rounded-2xl bg-[var(--surface-soft)] p-4"><p class="text-xs font-bold text-[var(--muted)]">{{ $label }}</p><p class="mt-2 font-black text-[var(--ink)]">{{ is_numeric($value) ? App\Support\ArabicFormat::money($value) : $value }}</p></div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--line)] pt-4"><p class="text-xs text-[var(--muted)]">دفع نقدي بتاريخ {{ App\Support\ArabicFormat::dateTime($participant->payment->paid_at) }} · المسترد {{ App\Support\ArabicFormat::money($participant->payment->refunds->sum('amount')) }}</p>@can('refunds.create')@if ($participant->payment->refunds->sum('amount') < $participant->payment->amount)<button class="btn btn-danger" type="button" wire:click="prepareRefund({{ $participant->payment->id }})">استرداد</button>@endif@endcan</div>
                    @can('refunds.create')
                        @if (($participant->payment?->practitionerEarning?->amount ?? 0) > 0)
                            <button class="btn btn-secondary" type="button" wire:click="prepareShareWaiver({{ $participant->payment->id }})">إرجاع حصة المعالج للمريض</button>
                        @endif
                    @endcan
                @else
                    @can('payments.create')
                        @php($previousPrice = $this->previousPrices->get($participant->patient_id))
                        <aside class="mb-4 rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-4">
                            @if ($previousPrice)
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div><p class="page-kicker">آخر سعر جلسة</p><p class="mt-1 text-xl font-bold text-[var(--ink)]">{{ App\Support\ArabicFormat::money($previousPrice->original_price) }}</p><p class="mt-1 text-xs text-[var(--muted)]">{{ $previousPrice->treatmentSession->practitioner?->name }} · {{ $previousPrice->treatmentSession->treatmentType?->name }} · {{ App\Support\ArabicFormat::appointmentDate($previousPrice->treatmentSession->started_at) }}@if($previousPrice->discount_amount) · خصم {{ App\Support\ArabicFormat::money($previousPrice->discount_amount) }}@endif</p></div>
                                    <button class="btn btn-secondary" type="button" wire:click="usePreviousPrice({{ $participant->id }})">استخدام آخر سعر</button>
                                </div>
                            @else
                                <p class="text-sm text-[var(--muted)]">لا يوجد سعر سابق لهذا المريض.</p>
                            @endif
                        </aside>
                        <form class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end" wire:submit="pay({{ $participant->id }})">
                            <label><span class="form-label">السعر الأصلي *</span><input class="form-input" type="number" min="1" wire:model="originalPrices.{{ $participant->id }}">@error("originalPrices.$participant->id")<span class="form-error">{{ $message }}</span>@enderror</label>
                            <label><span class="form-label">الخصم</span><input class="form-input" type="number" min="0" wire:model="discountAmounts.{{ $participant->id }}">@error("discountAmounts.$participant->id")<span class="form-error">{{ $message }}</span>@enderror</label>
                            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">تسجيل الدفع النقدي</button>
                        </form>
                    @else<div class="rounded-2xl bg-[var(--warning-soft)] p-4 text-sm font-bold text-[var(--warning)]">بانتظار تسجيل الدفع من موظف مخوّل.</div>@endcan
                @endif
            </article>
        @endforeach
    </section>

    @if ($waivingPaymentId)
        @php($waiverPayment = $treatmentSession->participants->pluck('payment')->filter()->firstWhere('id', $waivingPaymentId))
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/45 p-4" role="dialog" aria-modal="true">
            <form class="app-card w-full max-w-lg space-y-5 p-5 sm:p-6" wire:submit="waivePractitionerShare">
                <div><p class="page-kicker">إجراء مالي موثق</p><h2 class="mt-1 font-black text-[var(--ink)]">إرجاع حصة المعالج للمريض</h2></div>
                <div class="grid grid-cols-2 gap-3 text-sm"><div class="rounded-xl bg-[var(--surface-soft)] p-3"><p class="text-xs text-[var(--muted)]">المبلغ المدفوع</p><p class="mt-1 font-black">{{ number_format((int) ($waiverPayment?->amount ?? 0)) }} ل.س</p></div><div class="rounded-xl bg-[var(--surface-soft)] p-3"><p class="text-xs text-[var(--muted)]">حصة العيادة</p><p class="mt-1 font-black">{{ number_format((int) ($waiverPayment?->treatmentSessionParticipant?->clinic_share ?? 0)) }} ل.س</p></div><div class="rounded-xl bg-[var(--surface-soft)] p-3"><p class="text-xs text-[var(--muted)]">حصة المعالج الأصلية</p><p class="mt-1 font-black">{{ number_format((int) ($waiverPayment?->treatmentSessionParticipant?->practitioner_share ?? 0)) }} ل.س</p></div><div class="rounded-xl bg-[var(--surface-soft)] p-3"><p class="text-xs text-[var(--muted)]">المتاح للإرجاع</p><p class="mt-1 font-black text-[var(--danger)]">{{ number_format((int) ($waiverPayment?->practitionerEarning?->amount ?? 0)) }} ل.س</p></div></div>
                <label><span class="form-label">مبلغ الإرجاع *</span><input class="form-input" type="number" min="1" wire:model="waiverAmount">@error('waiverAmount')<span class="form-error">{{ $message }}</span>@enderror</label>
                <div class="flex flex-wrap gap-3"><button class="btn btn-danger" type="submit" wire:loading.attr="disabled">تأكيد الإرجاع</button><button class="btn btn-secondary" type="button" wire:click="$set('waivingPaymentId', null)">إلغاء</button></div>
            </form>
        </div>
    @endif

    @if ($refundingPaymentId)
        <form class="app-card border-2 border-[var(--danger)] p-5 sm:p-6" wire:submit="refund">
            <div class="mb-4"><p class="page-kicker">عملية عكسية موثقة</p><h2 class="mt-1 font-black text-[var(--ink)]">تسجيل استرداد</h2></div>
            <div class="grid gap-4 md:grid-cols-[1fr_2fr]"><label><span class="form-label">المبلغ *</span><input class="form-input" type="number" min="1" wire:model="refundAmount">@error('refundAmount')<span class="form-error">{{ $message }}</span>@enderror</label><label><span class="form-label">سبب الاسترداد (اختياري)</span><input class="form-input" wire:model="refundReason">@error('refundReason')<span class="form-error">{{ $message }}</span>@enderror</label></div>
            <div class="mt-4 flex gap-3"><button class="btn btn-danger" type="submit" wire:loading.attr="disabled">تأكيد الاسترداد</button><button class="btn btn-secondary" type="button" wire:click="$set('refundingPaymentId', null)">إلغاء</button></div>
        </form>
    @endif

    @if ($treatmentSession->participants->contains(fn ($participant) => auth()->user()->can('uploadAttachment', $participant->patient)))
        <form class="app-card space-y-4 p-5 sm:p-6" wire:submit.prevent="uploadAttachments">
            <div><p class="page-kicker">توثيق الجلسة</p><h2 class="mt-1 font-black text-[var(--ink)]">رفع ملفات للجلسة</h2><p class="mt-1 text-xs text-[var(--muted)]">العنوان والوصف اختياريان. تُحفظ الملفات في التخزين الخاص وتُربط تلقائيًا بهذه الجلسة.</p></div>
            @if ($errors->has('attachmentFiles') || $errors->has('attachmentFiles.*'))
                <div class="flash-message flash-error" role="alert">
                    <p class="text-sm font-semibold">{{ $errors->first('attachmentFiles') ?: $errors->first('attachmentFiles.*') }}</p>
                </div>
            @endif
            <div class="grid gap-4 md:grid-cols-2">
                @if ($treatmentSession->participants->count() === 1)
                    <div><span class="form-label">المريض</span><p class="form-input bg-[var(--surface-soft)]">{{ $treatmentSession->participants->first()->patient_name }}</p></div>
                @else
                <label>
                    <span class="form-label">المريض *</span>
                    <select class="form-select" wire:model="attachmentPatientId">
                        <option value="">اختر المريض</option>
                        @foreach($treatmentSession->participants as $participant)
                            <option value="{{ $participant->patient_id }}">{{ $participant->patient_name }}</option>
                        @endforeach
                    </select>
                    @error('attachmentPatientId')<span class="form-error">{{ $message }}</span>@enderror
                </label>
                @endif
                <label>
                    <span class="form-label">الملفات (حتى 25 م.ب للملف)</span>
                    <input class="form-input" type="file" multiple wire:model="attachmentFiles" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                    <span class="mt-1 block text-xs text-[var(--muted)]" wire:loading wire:target="attachmentFiles">جارٍ تجهيز الملف للرفع…</span>
                    @if ($attachmentFiles !== [])
                        <ul class="mt-2 space-y-1 text-xs font-bold text-[var(--ink-soft)]">
                            @foreach ($attachmentFiles as $pendingFile)
                                <li wire:key="session-pending-file-{{ $pendingFile->getFilename() }}">{{ $pendingFile->getClientOriginalName() }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @error('attachmentFiles')<span class="form-error">{{ $message }}</span>@enderror
                    @error('attachmentFiles.*')<span class="form-error">{{ $message }}</span>@enderror
                </label>
            </div>
            <button
                class="btn btn-secondary"
                type="submit"
                wire:key="session-attachment-submit-{{ count($attachmentFiles) }}"
                @disabled($attachmentFiles === [])
            >
                <span wire:loading.remove wire:target="uploadAttachments,attachmentFiles">رفع وربط بالجلسة</span>
                <span wire:loading wire:target="uploadAttachments,attachmentFiles">جارٍ الرفع والحفظ…</span>
            </button>
        </form>
    @endif

    @can('sessions.manage')
        @if ($treatmentSession->status !== App\Enums\TreatmentSessionStatus::Completed)
            @php($unpaidParticipants = $treatmentSession->participants->filter(fn ($participant) => $participant->payment_status !== App\Enums\ParticipantPaymentStatus::Paid))
            <section class="app-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-black text-[var(--ink)]">اعتماد اكتمال الجلسة</h2>
                    @if($unpaidParticipants->isNotEmpty())
                        <p class="mt-1 text-sm font-bold text-[var(--warning)]">توجد أرصدة غير مسددة قبل الإكمال:</p>
                        <ul class="mt-2 space-y-1 text-xs font-bold text-[var(--warning)]">
                            @foreach ($unpaidParticipants as $participant)
                                @php($outstanding = max(0, (int) $participant->final_amount - max(0, (int) ($participant->payment?->amount ?? 0) - (int) ($participant->payment?->refunds?->sum('amount') ?? 0))))
                                <li>{{ $participant->patient_name }} — {{ App\Support\ArabicFormat::money($outstanding) }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-1 text-xs text-[var(--muted)]">جميع المشاركين مستوفون ماليًا، ويمكن الآن اعتماد الجلسة.</p>
                    @endif
                </div>
                <button class="btn btn-primary w-full sm:w-auto" type="button" @disabled($unpaidParticipants->isNotEmpty()) x-on:click="$dispatch('confirm-action', { title: 'إكمال الجلسة؟', description: 'سيتم اعتماد الجلسة وتثبيت بياناتها المالية بعد التأكيد.', confirmLabel: 'تأكيد إكمال الجلسة', tone: 'primary', action: () => $wire.complete() })">إكمال الجلسة</button>
            </section>
        @endif
    @endcan
</div>
