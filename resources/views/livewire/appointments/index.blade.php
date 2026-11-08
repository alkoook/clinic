<div class="space-y-5">
    @error('operation')<div class="flash-message flash-error" role="alert">{{ $message }}</div>@enderror
    <header class="page-header">
        <div><p class="page-kicker">التشغيل اليومي</p><h1>المواعيد</h1><p>الموعد للحجز والتنظيم فقط؛ الأثر المالي يبدأ من الجلسة والدفع.</p></div>
        <div class="flex flex-wrap gap-2"><a class="btn btn-secondary" href="{{ route('appointments.calendar') }}" wire:navigate>عرض التقويم</a>@can('appointments.manage')<a class="btn btn-primary" href="{{ route('appointments.create') }}" wire:navigate>موعد جديد</a>@endcan</div>
    </header>

    <section class="app-card grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-6">
        <label class="sm:col-span-2 xl:col-span-1"><span class="form-label">البحث عن مريض</span><input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="ابحث باسم المريض..."></label>
        <label><span class="form-label">من</span><input class="form-input" type="date" wire:model.live="dateFrom"></label>
        <label><span class="form-label">إلى</span><input class="form-input" type="date" wire:model.live="dateTo"></label>
        @unless (auth()->user()->hasRole('practitioner'))
            <label><span class="form-label">الطبيب / المعالج</span><select class="form-select" wire:model.live="practitionerId"><option value="">الكل</option>@foreach ($this->practitioners as $practitioner)<option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>@endforeach</select></label>
        @endunless
        <label><span class="form-label">الغرفة</span><select class="form-select" wire:model.live="roomId"><option value="">كل الغرف</option>@foreach ($this->rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select></label>
        <label><span class="form-label">الحالة</span><select class="form-select" wire:model.live="status"><option value="">كل الحالات</option>@foreach (App\Enums\AppointmentStatus::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></label>
    </section>

    <section class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3" aria-label="قائمة المواعيد">
        @forelse ($this->appointments as $appointment)
            <article class="app-card status-surface {{ $appointment->status->badgeClass() }} overflow-hidden" wire:key="appointment-{{ $appointment->id }}">
                <div class="flex items-start justify-between gap-3 border-b border-[var(--line)] p-4 sm:p-5">
                    <div class="min-w-0">
                        <p class="text-xs font-black text-[var(--brand-strong)]">{{ App\Support\ArabicFormat::appointmentDate($appointment->starts_at) }}</p>
                        <h2 class="mt-1 truncate text-lg font-black text-[var(--ink)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') }}</h2>
                        <p class="mt-1 text-xs text-[var(--muted)]">{{ $appointment->kind->label() }} · {{ $appointment->participants->count() }} مشارك</p>
                    </div>
                    <x-ui.status-badge :status="$appointment->status" />
                </div>

                <dl class="grid grid-cols-2 gap-px bg-[var(--line)] text-sm">
                    <div class="bg-[var(--surface)] p-4"><dt class="form-label">الوقت</dt><dd class="mt-1 font-black text-[var(--ink)]" dir="ltr">{{ $appointment->starts_at->format('H:i') }} – {{ $appointment->ends_at->format('H:i') }}</dd><dd class="mt-1 text-xs text-[var(--muted)]">{{ $appointment->duration_minutes }} دقيقة</dd></div>
                    <div class="bg-[var(--surface)] p-4"><dt class="form-label">المعالج</dt><dd class="mt-1 font-bold text-[var(--ink)]">{{ $appointment->practitioner?->name }}</dd></div>
                    <div class="bg-[var(--surface)] p-4"><dt class="form-label">العلاج</dt><dd class="mt-1 font-bold text-[var(--ink)]">{{ $appointment->treatmentType?->name }}</dd></div>
                    <div class="bg-[var(--surface)] p-4"><dt class="form-label">الغرفة</dt><dd class="mt-1 font-bold text-[var(--ink)]">{{ $appointment->room?->name }}</dd></div>
                </dl>

                <div class="p-4 sm:p-5">
                    @can('appointments.manage')
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                            @if ($appointment->status === App\Enums\AppointmentStatus::Scheduled)
                                <button class="btn btn-sm btn-primary" type="button" wire:click="markStatus({{ $appointment->id }}, 'attended')"><x-ui.icon name="check" class="size-3.5" />حضر</button>
                                <button class="btn btn-sm btn-secondary" type="button" wire:click="markStatus({{ $appointment->id }}, 'no_show')"><x-ui.icon name="warning" class="size-3.5" />لم يحضر</button>
                                <a class="btn btn-sm btn-secondary" href="{{ route('appointments.reschedule', $appointment) }}" wire:navigate><x-ui.icon name="calendar" class="size-3.5" />إعادة جدولة</a>
                                <button class="btn btn-sm btn-danger" type="button" wire:click="openCancellation({{ $appointment->id }})"><x-ui.icon name="close" class="size-3.5" />إلغاء</button>
                            @elseif ($appointment->treatmentSession)
                                <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('sessions.show', $appointment->treatmentSession) }}" wire:navigate>فتح الجلسة</a>
                            @else
                                <span class="text-xs text-[var(--muted)]">لا توجد إجراءات متاحة.</span>
                            @endif
                        </div>
                    @else
                        <span class="text-xs text-[var(--muted)]">عرض فقط</span>
                    @endcan
                </div>

                @if ($cancellingId === $appointment->id)
                    <form class="space-y-3 border-t border-[var(--danger)]/20 bg-[var(--danger-soft)] p-4" wire:submit="cancel" wire:key="cancel-{{ $appointment->id }}">
                        <label><span class="form-label">سبب الإلغاء *</span><input class="form-input" wire:model="cancellationReason" placeholder="يُحفظ السبب في سجل الموعد">@error('cancellationReason')<span class="form-error">{{ $message }}</span>@enderror</label>
                        <div class="grid grid-cols-2 gap-2"><button class="btn btn-danger" type="submit">تأكيد الإلغاء</button><button class="btn btn-secondary" type="button" wire:click="$set('cancellingId', null)">تراجع</button></div>
                    </form>
                @endif
            </article>
        @empty
            <div class="empty-state lg:col-span-2 2xl:col-span-3">لا توجد مواعيد ضمن المرشحات المحددة.</div>
        @endforelse
    </section>
    {{ $this->appointments->links() }}
</div>
