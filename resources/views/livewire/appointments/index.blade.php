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

    <div class="table-shell">
        <table class="mobile-record-table"><thead><tr><th>الموعد</th><th>المريض / المرضى</th><th>المعالج</th><th>الغرفة والعلاج</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
            @forelse ($this->appointments as $appointment)
                <tr wire:key="appointment-{{ $appointment->id }}">
                    <td data-label="الموعد"><p class="font-black text-[var(--ink)]">{{ App\Support\ArabicFormat::appointmentDate($appointment->starts_at) }}</p><p class="text-xs text-[var(--muted)]">{{ $appointment->starts_at->translatedFormat('h:i a') }} — {{ $appointment->ends_at->translatedFormat('h:i a') }}</p></td>
                    <td data-label="المريض / المرضى"><p class="font-bold text-[var(--ink)]">{{ $appointment->participants->pluck('patient.full_name')->filter()->join('، ') }}</p><p class="text-xs text-[var(--muted)]">{{ $appointment->kind->label() }} · {{ $appointment->participants->count() }} مريض</p></td>
                    <td data-label="المعالج">{{ $appointment->practitioner?->name }}</td>
                    <td data-label="الغرفة والعلاج"><p>{{ $appointment->room?->name }}</p><p class="text-xs text-[var(--muted)]">{{ $appointment->treatmentType?->name }}</p></td>
                    <td data-label="الحالة"><span class="status-badge {{ $appointment->status === App\Enums\AppointmentStatus::Cancelled ? 'status-danger' : ($appointment->status === App\Enums\AppointmentStatus::Completed ? 'status-success' : '') }}">{{ $appointment->status->label() }}</span></td>
                    <td data-label="الإجراء">
                        @can('appointments.manage')
                            <div class="appointment-action-bar">
                                @if ($appointment->status === App\Enums\AppointmentStatus::Scheduled)
                                    <button class="btn btn-sm btn-secondary appointment-action" type="button" wire:click="markStatus({{ $appointment->id }}, 'attended')"><x-ui.icon name="check" class="size-3.5" />حضر</button>
                                    <button class="btn btn-sm btn-secondary appointment-action" type="button" wire:click="markStatus({{ $appointment->id }}, 'no_show')"><x-ui.icon name="warning" class="size-3.5" />لم يحضر</button>
                                    <a class="btn btn-sm btn-secondary appointment-action" href="{{ route('appointments.reschedule', $appointment) }}" wire:navigate><x-ui.icon name="calendar" class="size-3.5" />إعادة جدولة</a>
                                    <button class="btn btn-sm btn-danger appointment-action" type="button" wire:click="openCancellation({{ $appointment->id }})"><x-ui.icon name="close" class="size-3.5" />إلغاء</button>
                                @elseif ($appointment->treatmentSession)
                                    <a class="btn btn-secondary" href="{{ route('sessions.show', $appointment->treatmentSession) }}" wire:navigate>الجلسة</a>
                                @else
                                    <span class="text-xs text-[var(--muted)]">لا إجراء</span>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-[var(--muted)]">عرض فقط</span>
                        @endcan
                    </td>
                </tr>
                @if ($cancellingId === $appointment->id)
                    <tr wire:key="cancel-{{ $appointment->id }}"><td colspan="6"><form class="flex flex-col gap-3 rounded-2xl bg-[var(--danger-soft)] p-4 sm:flex-row" wire:submit="cancel"><label class="flex-1"><span class="form-label">سبب الإلغاء *</span><input class="form-input" wire:model="cancellationReason" placeholder="يُحفظ السبب في سجل الموعد">@error('cancellationReason')<span class="form-error">{{ $message }}</span>@enderror</label><div class="flex items-end gap-2"><button class="btn btn-danger" type="submit">تأكيد الإلغاء</button><button class="btn btn-secondary" type="button" wire:click="$set('cancellingId', null)">تراجع</button></div></form></td></tr>
                @endif
            @empty
                <tr><td colspan="6"><div class="empty-state">لا توجد مواعيد ضمن المرشحات المحددة.</div></td></tr>
            @endforelse
        </tbody></table>
    </div>
    {{ $this->appointments->links() }}
</div>
