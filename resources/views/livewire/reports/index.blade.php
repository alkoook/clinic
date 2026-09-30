<div class="space-y-6">
    <header class="page-header">
        <div>
            <p class="page-kicker">بحث وتصدير</p>
            <h1>التقارير</h1>
            <p>اعرض السجلات وفق الفترة والمعايير المطلوبة، ثم صدّر النتيجة الحالية إلى Excel.</p>
        </div>
        <button class="btn btn-primary" type="button" wire:click="requestExport" wire:loading.attr="disabled" wire:target="requestExport">
            <span wire:loading.remove wire:target="requestExport">تصدير Excel</span>
            <span wire:loading wire:target="requestExport">جارٍ إرسال الطلب…</span>
        </button>
    </header>

    @php
        $reportOptions = auth()->user()->can('reports.view')
            ? ['patients' => 'المرضى', 'appointments' => 'المواعيد', 'sessions' => 'الجلسات', 'financial' => 'المالية', 'expenses' => 'المصاريف', 'practitioners' => 'المعالجون', 'cashbox' => 'الصناديق', 'weekly_close' => 'الإغلاق الأسبوعي', 'room_utilization' => 'استخدام الغرف', 'specialty_treatment' => 'الاختصاص والعلاج', 'refunds_adjustments' => 'الاستردادات والتعديلات']
            : ['appointments' => 'المواعيد', 'sessions' => 'الجلسات', 'practitioners' => 'مستحقاتي'];
    @endphp

    <section class="app-card space-y-5 p-4 sm:p-5">
        <div class="no-scrollbar flex max-w-full gap-2 overflow-x-auto pb-1" role="tablist" aria-label="نوع التقرير">
            @foreach ($reportOptions as $value => $label)
                <button
                    class="btn shrink-0 {{ $reportType === $value ? 'btn-primary' : 'btn-secondary' }}"
                    type="button"
                    role="tab"
                    aria-selected="{{ $reportType === $value ? 'true' : 'false' }}"
                    wire:click="$set('reportType', '{{ $value }}')"
                    wire:key="report-type-{{ $value }}"
                >{{ $label }}</button>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <label><span class="form-label">من تاريخ</span><input class="form-input" type="date" wire:model.live="dateFrom"></label>
            <label><span class="form-label">إلى تاريخ</span><input class="form-input" type="date" wire:model.live="dateTo"></label>

            @can('reports.view')
                <label>
                    <span class="form-label">الطبيب / المعالج</span>
                    <select class="form-select" wire:model.live="practitionerId">
                        <option value="">الكل</option>
                        @foreach ($this->practitioners as $practitioner)
                            <option value="{{ $practitioner->id }}">{{ $practitioner->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endcan

            @if (in_array($reportType, ['appointments', 'sessions'], true))
                <label>
                    <span class="form-label">الغرفة</span>
                    <select class="form-select" wire:model.live="roomId"><option value="">كل الغرف</option>@foreach ($this->rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select>
                </label>
                <label>
                    <span class="form-label">نوع العلاج</span>
                    <select class="form-select" wire:model.live="treatmentTypeId"><option value="">كل العلاجات</option>@foreach ($this->treatmentTypes as $treatmentType)<option value="{{ $treatmentType->id }}">{{ $treatmentType->name }}</option>@endforeach</select>
                </label>
            @endif

            @if ($reportType === 'patients')
                <label>
                    <span class="form-label">بحث المريض</span>
                    <input class="form-input" type="search" wire:model.live.debounce.300ms="search" placeholder="الاسم، الملف، الهاتف">
                </label>
                <label>
                    <span class="form-label">مصدر المريض</span>
                    <select class="form-select" wire:model.live="source"><option value="">كل المصادر</option>@foreach (App\Enums\PatientSource::cases() as $sourceOption)<option value="{{ $sourceOption->value }}">{{ $sourceOption->label() }}</option>@endforeach</select>
                </label>
            @elseif ($reportType === 'appointments')
                <label>
                    <span class="form-label">حالة الموعد</span>
                    <select class="form-select" wire:model.live="status"><option value="">كل الحالات</option>@foreach (App\Enums\AppointmentStatus::cases() as $statusOption)<option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>@endforeach</select>
                </label>
            @elseif ($reportType === 'expenses')
                <label>
                    <span class="form-label">تصنيف المصروف</span>
                    <select class="form-select" wire:model.live="categoryId"><option value="">كل التصنيفات</option>@foreach ($this->categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                </label>
            @elseif ($reportType === 'cashbox')
                <label><span class="form-label">نوع الحركة</span><select class="form-select" wire:model.live="status"><option value="">كل الحركات</option>@foreach (App\Enums\TransactionType::cases() as $transactionType)<option value="{{ $transactionType->value }}">{{ $transactionType->label() }}</option>@endforeach</select></label>
            @endif
        </div>
    </section>

    @if ($this->recentExports->isNotEmpty())
        <section class="app-card p-4 sm:p-5" aria-labelledby="recent-exports-heading">
            <div class="mb-4 flex items-center justify-between gap-3"><div><p class="page-kicker">يعمل عبر Queue</p><h2 id="recent-exports-heading" class="font-black text-[var(--ink)]">آخر ملفات التصدير</h2></div><span class="status-badge">صلاحية 48 ساعة</span></div>
            <div class="grid min-w-0 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->recentExports as $export)
                    <article class="interactive-report-export flex w-full min-w-0 items-center justify-between gap-3 overflow-hidden rounded-2xl border border-[var(--line)] bg-[var(--surface-soft)] p-3" wire:key="report-export-{{ $export->id }}">
                        <div class="min-w-0"><p class="truncate text-sm font-black text-[var(--ink)]">{{ $export->download_name }}</p><p class="mt-1 text-xs text-[var(--muted)]">{{ $export->created_at->diffForHumans() }}</p></div>
                        @if ($export->status === 'completed' && $export->expires_at?->isFuture())
                            <a class="btn btn-secondary shrink-0" href="{{ route('reports.exports.download', $export) }}">تنزيل</a>
                        @elseif ($export->status === 'failed')
                            <span class="status-badge status-danger">متعذر</span>
                        @else
                            <span class="status-badge status-warning"><span class="size-2 animate-pulse rounded-full bg-current"></span>جارٍ التجهيز</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <div wire:loading.delay class="flash-message flash-warning" wire:target="reportType,dateFrom,dateTo,practitionerId,roomId,treatmentTypeId,categoryId,source,status,search">جارٍ تحديث التقرير…</div>

    <div class="table-shell responsive-report-table report-{{ $reportType }}" wire:loading.class="opacity-60" wire:target="reportType,dateFrom,dateTo,practitionerId,roomId,treatmentTypeId,categoryId,source,status,search">
        @if ($reportType === 'patients')
            <table>
                <thead><tr><th>رقم الملف</th><th>المريض</th><th>الهاتف</th><th>المصدر</th><th>المعالج المُحيل</th><th>المشفى</th><th>الحالة</th><th>تاريخ التسجيل</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr wire:key="report-patient-{{ $row->id }}">
                            <td class="font-black text-[var(--brand-strong)]">{{ $row->medical_file_number }}</td>
                            <td class="font-bold text-[var(--ink)]">{{ $row->full_name }}</td>
                            <td dir="ltr" class="text-right">{{ $row->phone ?: '—' }}</td>
                            <td>{{ $row->source->label() }}</td>
                            <td>{{ $row->referringPractitioner?->name ?: '—' }}</td>
                            <td>{{ $row->incoming_hospital_name ?: '—' }}</td>
                            <td><span class="status-badge {{ $row->is_active ? 'status-success' : 'status-danger' }}">{{ $row->is_active ? 'نشط' : 'غير نشط' }}</span></td>
                            <td class="whitespace-nowrap">{{ $row->created_at->format('Y/m/d H:i') }}</td>
                        </tr>
                    @empty<tr><td colspan="8"><div class="empty-state">لا توجد بيانات مرضى مطابقة.</div></td></tr>@endforelse
                </tbody>
            </table>
        @elseif ($reportType === 'appointments')
            <table>
                <thead><tr><th>الموعد</th><th>التاريخ والوقت</th><th>المعالج</th><th>الغرفة</th><th>العلاج</th><th>النوع</th><th>المرضى</th><th>الحالة</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr wire:key="report-appointment-{{ $row->id }}">
                            <td class="font-black text-[var(--brand-strong)]">#{{ $row->id }}</td>
                            <td class="whitespace-nowrap"><p>{{ $row->starts_at->format('Y/m/d') }}</p><p class="text-xs text-[var(--muted)]" dir="ltr">{{ $row->starts_at->format('H:i') }} — {{ $row->ends_at->format('H:i') }}</p></td>
                            <td class="font-bold text-[var(--ink)]">{{ $row->practitioner->name }}</td>
                            <td>{{ $row->room->name }}</td>
                            <td>{{ $row->treatmentType->name }}</td>
                            <td>{{ $row->kind->label() }}</td>
                            <td class="max-w-sm">{{ $row->participants->pluck('patient.full_name')->filter()->implode('، ') ?: '—' }}</td>
                            <td><span class="status-badge">{{ $row->status->label() }}</span></td>
                        </tr>
                    @empty<tr><td colspan="8"><div class="empty-state">لا توجد مواعيد مطابقة.</div></td></tr>@endforelse
                </tbody>
            </table>
        @elseif ($reportType === 'sessions')
            <table>
                <thead><tr><th>الجلسة</th><th>التاريخ</th><th>المريض</th><th>المعالج</th><th>العلاج والغرفة</th><th>السعر</th><th>الخصم</th><th>النهائي</th><th>حصة المعالج</th>@can('reports.view')<th>حصة العيادة</th>@endcan<th>الدفع</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr wire:key="report-session-participant-{{ $row->id }}">
                            <td><a class="font-black text-[var(--brand-strong)]" href="{{ route('sessions.show', $row->treatmentSession) }}" wire:navigate>#{{ $row->treatment_session_id }}</a></td>
                            <td class="whitespace-nowrap">{{ $row->treatmentSession->started_at->format('Y/m/d H:i') }}</td>
                            <td class="font-bold text-[var(--ink)]">{{ $row->patient_name }}</td>
                            <td>{{ $row->treatmentSession->practitioner_name }}</td>
                            <td><p>{{ $row->treatmentSession->treatment_type_name }}</p><p class="text-xs text-[var(--muted)]">{{ $row->treatmentSession->room_name }}</p></td>
                            <td>{{ number_format($row->original_price) }} ل.س</td>
                            <td class="text-[var(--danger)]">{{ number_format($row->discount_amount) }} ل.س</td>
                            <td class="font-bold text-[var(--ink)]">{{ number_format($row->final_amount) }} ل.س</td>
                            <td>{{ number_format($row->practitioner_share) }} ل.س</td>
                            @can('reports.view')<td>{{ number_format($row->clinic_share) }} ل.س</td>@endcan
                            <td><span class="status-badge {{ $row->payment_status === App\Enums\ParticipantPaymentStatus::Paid ? 'status-success' : 'status-warning' }}">{{ $row->payment_status->label() }}</span></td>
                        </tr>
                    @empty<tr><td colspan="{{ auth()->user()->can('reports.view') ? 11 : 10 }}"><div class="empty-state">لا توجد جلسات مطابقة.</div></td></tr>@endforelse
                </tbody>
            </table>
        @elseif ($reportType === 'financial')
            <table>
                <thead><tr><th>الدفعة</th><th>التاريخ</th><th>المريض</th><th>المعالج</th><th>المقبوض</th><th>حصة المعالج</th><th>حصة العيادة</th><th>المسترد</th><th>الصافي المقبوض</th><th>الحالة</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        @php
                            $refundedAmount = (int) $row->refunds->sum('amount');
                            $paymentStatusLabel = match ($row->status) {
                                App\Enums\PaymentStatus::Paid => 'مدفوعة',
                                App\Enums\PaymentStatus::PartiallyRefunded => 'مستردة جزئياً',
                                App\Enums\PaymentStatus::Refunded => 'مستردة بالكامل',
                                App\Enums\PaymentStatus::Voided => 'ملغاة',
                            };
                        @endphp
                        <tr wire:key="report-payment-{{ $row->id }}">
                            <td class="font-black text-[var(--brand-strong)]">#{{ $row->id }}</td>
                            <td class="whitespace-nowrap">{{ $row->paid_at->format('Y/m/d H:i') }}</td>
                            <td class="font-bold text-[var(--ink)]">{{ $row->treatmentSessionParticipant->patient_name }}</td>
                            <td>{{ $row->treatmentSessionParticipant->treatmentSession->practitioner_name }}</td>
                            <td class="text-[var(--success)]">{{ number_format($row->amount) }} ل.س</td>
                            <td>{{ number_format($row->treatmentSessionParticipant->practitioner_share) }} ل.س</td>
                            <td>{{ number_format($row->treatmentSessionParticipant->clinic_share) }} ل.س</td>
                            <td class="text-[var(--danger)]">{{ number_format($refundedAmount) }} ل.س</td>
                            <td class="font-black text-[var(--ink)]">{{ number_format($row->amount - $refundedAmount) }} ل.س</td>
                            <td><span class="status-badge {{ $row->status === App\Enums\PaymentStatus::Paid ? 'status-success' : 'status-warning' }}">{{ $paymentStatusLabel }}</span></td>
                        </tr>
                    @empty<tr><td colspan="10"><div class="empty-state">لا توجد دفعات مطابقة.</div></td></tr>@endforelse
                </tbody>
            </table>
        @elseif ($reportType === 'expenses')
            <table>
                <thead><tr><th>المصروف</th><th>التاريخ</th><th>التصنيف</th><th>الصندوق</th><th>المبلغ</th><th>الوصف</th><th>صافي الأسبوع</th><th>المسجّل</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr wire:key="report-expense-{{ $row->id }}">
                            <td class="font-black text-[var(--brand-strong)]">#{{ $row->id }}</td>
                            <td class="whitespace-nowrap">{{ $row->occurred_at->format('Y/m/d H:i') }}</td>
                            <td>{{ $row->category->name }}</td>
                            <td>{{ $row->cashbox->name }}</td>
                            <td class="font-black text-[var(--danger)]">{{ number_format($row->amount) }} ل.س</td>
                            <td class="max-w-sm">{{ $row->description ?: '—' }}</td>
                            <td><span class="status-badge {{ $row->affects_weekly_net ? 'status-warning' : '' }}">{{ $row->affects_weekly_net ? 'محتسب' : 'غير محتسب' }}</span></td>
                            <td>{{ $row->creator?->name ?: 'النظام' }}</td>
                        </tr>
                    @empty<tr><td colspan="8"><div class="empty-state">لا توجد مصاريف مطابقة.</div></td></tr>@endforelse
                </tbody>
            </table>
        @elseif ($reportType === 'practitioners')
            <table>
                <thead><tr><th>التاريخ</th><th>المعالج</th><th>المريض</th><th>نوع العلاج</th><th>المبلغ المعالج</th><th>النسبة</th><th>المستحق</th><th>الحالة</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        @php
                            $earningStatusLabel = match ($row->status) {
                                'accrued' => 'مستحق',
                                'partially_refunded' => 'مسترد جزئياً',
                                'refunded' => 'مسترد بالكامل',
                                default => $row->status,
                            };
                        @endphp
                        <tr wire:key="report-earning-{{ $row->id }}">
                            <td class="whitespace-nowrap">{{ $row->earned_at->format('Y/m/d H:i') }}</td>
                            <td class="font-bold text-[var(--ink)]">{{ $row->practitioner->name }}</td>
                            <td>{{ $row->treatmentSessionParticipant->patient_name }}</td>
                            <td>{{ $row->treatmentSessionParticipant->treatmentSession->treatment_type_name }}</td>
                            <td>{{ number_format($row->gross_amount) }} ل.س</td>
                            <td>{{ number_format((float) $row->practitioner_percentage, 2) }}%</td>
                            <td class="font-black text-[var(--success)]">{{ number_format($row->amount) }} ل.س</td>
                            <td><span class="status-badge {{ $row->status === 'accrued' ? 'status-success' : 'status-warning' }}">{{ $earningStatusLabel }}</span></td>
                        </tr>
                    @empty<tr><td colspan="8"><div class="empty-state">لا توجد مستحقات مطابقة.</div></td></tr>@endforelse
                </tbody>
            </table>
        @elseif ($reportType === 'cashbox')
            <table><thead><tr><th>الحركة</th><th>التاريخ</th><th>الصندوق</th><th>النوع</th><th>الاتجاه</th><th>المبلغ</th><th>الوصف</th><th>المسجّل</th></tr></thead><tbody>
            @forelse ($this->rows as $row)<tr wire:key="cashbox-report-{{ $row->id }}"><td class="font-black text-[var(--brand-strong)]">#{{ $row->id }}</td><td>{{ $row->occurred_at->format('Y/m/d H:i') }}</td><td>{{ $row->cashbox->name }}</td><td>{{ $row->type->label() }}</td><td><span class="status-badge {{ $row->direction->value === 'in' ? 'status-success' : 'status-danger' }}">{{ $row->direction->value === 'in' ? 'داخل' : 'خارج' }}</span></td><td class="font-black">{{ number_format($row->amount) }} ل.س</td><td>{{ $row->description ?: '—' }}</td><td>{{ $row->creator?->name ?: 'النظام' }}</td></tr>@empty<tr><td colspan="8"><div class="empty-state">لا توجد حركات صندوق مطابقة.</div></td></tr>@endforelse
            </tbody></table>
        @elseif ($reportType === 'weekly_close')
            <table><thead><tr><th>الأسبوع</th><th>الجلسات</th><th>التحصيلات</th><th>الاستردادات</th><th>المستحقات</th><th>المصروفات</th><th>الصافي</th><th>الفائض/العجز</th><th>الإغلاق</th></tr></thead><tbody>
            @forelse ($this->rows as $row)<tr wire:key="weekly-report-{{ $row->id }}"><td class="font-bold">{{ $row->financialWeek->starts_on->format('Y/m/d') }} — {{ $row->financialWeek->ends_on->format('Y/m/d') }}</td><td>{{ number_format($row->paid_sessions_count) }}</td><td class="text-[var(--success)]">{{ number_format($row->collections) }}</td><td class="text-[var(--danger)]">{{ number_format($row->refunds) }}</td><td>{{ number_format($row->practitioner_shares) }}</td><td>{{ number_format($row->expenses) }}</td><td class="font-black">{{ number_format($row->calculated_net) }}</td><td>{{ number_format($row->transferred_surplus) }} / {{ number_format($row->deficit_covered) }}</td><td>{{ $row->closed_at->format('Y/m/d H:i') }}</td></tr>@empty<tr><td colspan="9"><div class="empty-state">لا توجد إغلاقات أسبوعية بعد.</div></td></tr>@endforelse
            </tbody></table>
        @elseif ($reportType === 'room_utilization')
            <table><thead><tr><th>الغرفة</th><th>المواعيد</th><th>المكتملة</th><th>الوقت المحجوز</th><th>الاستخدام</th><th>الإلغاء</th><th>عدم الحضور</th><th>الإيراد</th><th>متوسط الجلسة</th><th>الاتجاه</th></tr></thead><tbody>
            @forelse ($this->rows as $row)
                @php
                    $capacity = max(1, \Carbon\CarbonImmutable::parse($dateFrom)->diffInDays(\Carbon\CarbonImmutable::parse($dateTo)) + 1) * 480;
                    $utilization = min(100, (($row->booked_minutes ?? 0) / $capacity) * 100);
                    $cancelRate = $row->appointments_count ? ($row->cancelled_count / $row->appointments_count) * 100 : 0;
                    $noShowRate = $row->appointments_count ? ($row->no_show_count / $row->appointments_count) * 100 : 0;
                    $average = $row->completed_sessions_count ? $row->room_revenue / $row->completed_sessions_count : 0;
                    $trend = $row->earlier_sessions_count ? (($row->recent_sessions_count - $row->earlier_sessions_count) / $row->earlier_sessions_count) * 100 : null;
                @endphp
                <tr wire:key="room-utilization-{{ $row->id }}"><td class="font-black text-[var(--ink)]">{{ $row->name }}</td><td>{{ number_format($row->appointments_count) }}</td><td>{{ number_format($row->completed_sessions_count) }}</td><td>{{ number_format(($row->booked_minutes ?? 0) / 60, 1) }} ساعة</td><td><span class="status-badge status-success">{{ number_format($utilization, 1) }}%</span></td><td>{{ number_format($cancelRate, 1) }}%</td><td>{{ number_format($noShowRate, 1) }}%</td><td class="font-black">{{ number_format($row->room_revenue) }} ل.س</td><td>{{ number_format($average) }} ل.س</td><td>{{ $trend === null ? ($row->recent_sessions_count ? 'جديد' : 'مستقر') : (($trend > 0 ? '+' : '').number_format($trend, 1).'%') }}</td></tr>
            @empty<tr><td colspan="10"><div class="empty-state">لا توجد غرف لعرض استخدامها.</div></td></tr>@endforelse
            </tbody></table>
        @elseif ($reportType === 'specialty_treatment')
            <table><thead><tr><th>الاختصاص / العلاج</th><th>المرضى</th><th>الجلسات</th><th>الإيراد</th><th>المتوسط</th><th>جدد / عائدون</th><th>المعالجون</th><th>الاتجاه</th></tr></thead><tbody>
            @forelse ($this->rows as $row)
                @php($trend = $row->earlier_sessions_count ? (($row->recent_sessions_count - $row->earlier_sessions_count) / $row->earlier_sessions_count) * 100 : null)
                <tr wire:key="treatment-performance-{{ $row->id }}"><td><span class="text-xs text-[var(--muted)]">{{ $row->specialty?->name ?: 'غير محدد' }}</span><strong class="mt-1 block text-[var(--ink)]">{{ $row->name }}</strong></td><td>{{ number_format($row->patients_count) }}</td><td>{{ number_format($row->sessions_count) }}</td><td class="font-black">{{ number_format($row->treatment_revenue) }} ل.س</td><td>{{ number_format($row->sessions_count ? $row->treatment_revenue / $row->sessions_count : 0) }} ل.س</td><td>{{ number_format($row->new_patients_count) }} / {{ number_format(max(0, $row->patients_count - $row->new_patients_count)) }}</td><td>{{ $row->practitioner_distribution ?: '—' }}</td><td><span class="status-badge">{{ $trend === null ? ($row->recent_sessions_count ? 'جديد' : 'مستقر') : (($trend > 0 ? '+' : '').number_format($trend, 1).'%') }}</span></td></tr>
            @empty<tr><td colspan="8"><div class="empty-state">لا توجد أنواع علاج.</div></td></tr>@endforelse
            </tbody></table>
        @else
            <table><thead><tr><th>التعديل</th><th>التاريخ</th><th>النوع</th><th>المعالج</th><th>الأسبوع</th><th>المبلغ</th><th>السبب</th><th>المسجّل</th></tr></thead><tbody>
            @forelse ($this->rows as $row)<tr wire:key="adjustment-report-{{ $row->id }}"><td class="font-black text-[var(--brand-strong)]">#{{ $row->id }}</td><td>{{ $row->occurred_at->format('Y/m/d H:i') }}</td><td>{{ $row->type->label() }}</td><td>{{ $row->practitioner?->name ?: 'العيادة' }}</td><td>{{ $row->financialWeek->starts_on->format('Y/m/d') }}</td><td class="font-black {{ $row->amount < 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]' }}">{{ number_format($row->amount) }} ل.س</td><td>{{ $row->reason }}</td><td>{{ $row->creator?->name ?: 'النظام' }}</td></tr>@empty<tr><td colspan="8"><div class="empty-state">لا توجد استردادات أو تعديلات مطابقة.</div></td></tr>@endforelse
            </tbody></table>
        @endif
    </div>
    {{ $this->rows->links() }}
</div>
