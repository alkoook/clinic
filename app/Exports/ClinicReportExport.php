<?php

namespace App\Exports;

use App\Enums\ClinicNotificationType;
use App\Models\Appointment;
use App\Models\CashboxTransaction;
use App\Models\Expense;
use App\Models\FinancialAdjustment;
use App\Models\FinancialAuditLog;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PractitionerEarning;
use App\Models\ReportExport;
use App\Models\TreatmentSessionParticipant;
use App\Models\WeeklyClosing;
use App\Notifications\ClinicEventNotification;
use App\Services\AuditLogQuery;
use App\Services\ReportAnalyticsService;
use App\Services\SettingsService;
use App\Support\ArabicFormat;
use App\Support\PresentationLabels;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Throwable;

class ClinicReportExport extends DefaultValueBinder implements FromQuery, ShouldQueue, WithCustomStartCell, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle
{
    use Queueable;

    /** @param array<string, mixed> $filters */
    public function __construct(
        private readonly string $type,
        private readonly array $filters,
        private readonly bool $includeClinicShare = false,
        private readonly ?int $reportExportId = null,
        private readonly string $generatedBy = '',
    ) {
        $this->onQueue('exports');
    }

    public function startCell(): string
    {
        return 'A7';
    }

    public function title(): string
    {
        return mb_substr($this->reportTitle(), 0, 31);
    }

    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = max(7, $sheet->getHighestRow());
                $sheet->setRightToLeft(true);
                $sheet->setCellValue('A1', app(SettingsService::class)->string('clinic.name', (string) config('clinic.name')));
                $sheet->setCellValue('A2', $this->reportTitle());
                $sheet->setCellValue('A3', 'الفترة: '.($this->filter('from') ?: 'البداية').' — '.($this->filter('to') ?: 'اليوم'));
                $sheet->setCellValue('A4', 'تاريخ الإنشاء: '.now()->format('Y-m-d H:i'));
                $sheet->setCellValue('A5', 'أنشأه: '.($this->generatedBy ?: 'مستخدم النظام'));
                $sheet->mergeCells("A1:{$lastColumn}1");
                $sheet->mergeCells("A2:{$lastColumn}2");
                $sheet->mergeCells("A3:{$lastColumn}3");
                $sheet->mergeCells("A4:{$lastColumn}4");
                $sheet->mergeCells("A5:{$lastColumn}5");
                $sheet->getStyle("A1:{$lastColumn}2")->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0369A1');
                $sheet->getStyle("A7:{$lastColumn}7")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0369A1']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);
                $sheet->getStyle("A7:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE7EC');
                $sheet->getStyle("A8:{$lastColumn}{$lastRow}")->getAlignment()->setVertical('center');
                $sheet->freezePane('A8');
                $sheet->setAutoFilter("A7:{$lastColumn}{$lastRow}");
                $sheet->getRowDimension(7)->setRowHeight(26);

                foreach (range('A', $lastColumn) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }
            },
        ];
    }

    public function failed(Throwable $exception): void
    {
        if ($this->reportExportId === null) {
            return;
        }

        $reportExport = ReportExport::query()->with('requester')->find($this->reportExportId);

        if ($reportExport === null) {
            return;
        }

        $reportExport->update(['status' => 'failed', 'error' => 'تعذر إنشاء التقرير. حاول مرة أخرى.', 'completed_at' => now()]);
        $reportExport->requester->notify(new ClinicEventNotification(
            ClinicNotificationType::ReportExportFailed,
            'تعذر إنشاء التقرير',
            'لم نتمكن من تجهيز التقرير المطلوب. يمكنك المحاولة مرة أخرى.',
            route('reports.index'),
            ['report_export_id' => $reportExport->getKey()],
        ));
    }

    private function reportTitle(): string
    {
        return match ($this->type) {
            'patients' => 'تقرير المرضى',
            'appointments' => 'تقرير المواعيد',
            'sessions' => 'تقرير الجلسات',
            'financial' => 'التقرير المالي',
            'expenses' => 'تقرير المصروفات',
            'practitioners' => 'تقرير مستحقات المعالجين',
            'cashbox' => 'تقرير حركات الصندوق',
            'weekly_close' => 'تقرير الإغلاق الأسبوعي',
            'room_utilization' => 'تقرير استخدام الغرف',
            'specialty_treatment' => 'تقرير الاختصاصات والعلاجات',
            'refunds_adjustments' => 'تقرير الاستردادات والتعديلات',
            'audit' => 'تقرير سجل التدقيق',
            default => 'تقرير العيادة',
        };
    }

    public function query(): Builder
    {
        return match ($this->type) {
            'patients' => $this->patientsQuery(),
            'appointments' => $this->appointmentsQuery(),
            'sessions' => $this->sessionsQuery(),
            'financial' => $this->financialQuery(),
            'expenses' => $this->expensesQuery(),
            'practitioners' => $this->practitionerEarningsQuery(),
            'cashbox' => $this->cashboxQuery(),
            'weekly_close' => $this->weeklyClosingQuery(),
            'room_utilization' => $this->roomUtilizationQuery(),
            'specialty_treatment' => $this->treatmentPerformanceQuery(),
            'refunds_adjustments' => $this->adjustmentsQuery(),
            'audit' => $this->auditQuery(),
            default => Patient::query()->whereRaw('1 = 0'),
        };
    }

    /** @return list<string> */
    public function headings(): array
    {
        return match ($this->type) {
            'patients' => ['رقم الإضبارة', 'اسم المريض', 'الجوال', 'المصدر', 'الطبيب المُحيل', 'المشفى', 'الحالة', 'تاريخ التسجيل'],
            'appointments' => ['الرقم', 'التاريخ', 'من', 'إلى', 'الطبيب / المعالج', 'الغرفة', 'نوع الجلسة', 'المرضى', 'الحالة'],
            'sessions' => [
                'رقم الجلسة',
                'التاريخ',
                'المريض',
                'الطبيب / المعالج',
                'العلاج',
                'الغرفة',
                'السعر الأصلي',
                'الخصم',
                'النهائي',
                'حصة الطبيب',
                ...($this->includeClinicShare ? ['حصة العيادة'] : []),
                'الدفع',
            ],
            'financial' => [
                'رقم الدفعة',
                'التاريخ',
                'المريض',
                'الطبيب / المعالج',
                'المقبوض',
                'حصة الطبيب',
                ...($this->includeClinicShare ? ['حصة العيادة'] : []),
                'المسترد',
                'الحالة',
            ],
            'expenses' => ['الرقم', 'التاريخ', 'التصنيف', 'الصندوق', 'المبلغ', 'الوصف', 'يدخل في صافي الأسبوع', 'المسجل'],
            'practitioners' => ['التاريخ', 'الطبيب / المعالج', 'المريض', 'نوع الجلسة', 'المبلغ المعالج', 'النسبة', 'المستحق'],
            'cashbox' => ['الرقم', 'التاريخ', 'الصندوق', 'نوع الحركة', 'الاتجاه', 'المبلغ', 'الوصف', 'المسجل'],
            'weekly_close' => ['الأسبوع', 'الجلسات المدفوعة', 'التحصيلات', 'الاستردادات', 'مستحقات المعالجين', 'المصروفات', 'التعديلات', 'الصافي', 'الفائض المحول', 'تغطية العجز', 'تاريخ الإغلاق'],
            'room_utilization' => ['الغرفة', 'المواعيد', 'الجلسات المكتملة', 'الدقائق المحجوزة', 'الساعات', 'نسبة الاستخدام', 'نسبة الإلغاء', 'نسبة عدم الحضور', 'الإيراد', 'متوسط الجلسة', 'اتجاه الفترة'],
            'specialty_treatment' => ['الاختصاص', 'العلاج', 'المرضى', 'الجلسات', 'الإيراد', 'متوسط الجلسة', 'مرضى جدد', 'مرضى عائدون', 'توزيع المعالجين', 'الاتجاه', 'نوع الجلسة'],
            'refunds_adjustments' => ['الرقم', 'التاريخ', 'النوع', 'المعالج', 'الأسبوع', 'المبلغ', 'السبب', 'المسجل'],
            'audit' => ['التاريخ', 'الوقت', 'المنفذ', 'نوع العملية', 'القسم', 'الوصف', 'المبلغ', 'تفاصيل التغيير'],
            default => [],
        };
    }

    /** @return list<int|string|null> */
    public function map(mixed $row): array
    {
        return match ($this->type) {
            'patients' => [
                $row->medical_file_number,
                $row->full_name,
                $row->phone,
                $row->source->label(),
                $row->referringPractitioner?->name,
                $row->incoming_hospital_name,
                $row->is_active ? 'نشط' : 'غير نشط',
                $row->created_at?->format('Y-m-d H:i'),
            ],
            'appointments' => [
                $row->id,
                ArabicFormat::appointmentDate($row->starts_at),
                $row->starts_at->format('H:i'),
                $row->ends_at->format('H:i'),
                $row->practitioner->name,
                $row->room->name,
                $row->treatmentType->name,
                $row->participants->pluck('patient.full_name')->filter()->implode('، '),
                $row->status->label(),
            ],
            'sessions' => [
                $row->treatment_session_id,
                $row->treatmentSession->started_at->format('Y-m-d H:i'),
                $row->patient_name,
                $row->treatmentSession->practitioner_name,
                $row->treatmentSession->treatment_type_name,
                $row->treatmentSession->room_name,
                $row->original_price,
                $row->discount_amount,
                $row->final_amount,
                $row->practitioner_share,
                ...($this->includeClinicShare ? [$row->clinic_share] : []),
                $row->payment_status->label(),
            ],
            'financial' => [
                $row->id,
                $row->paid_at->format('Y-m-d H:i'),
                $row->treatmentSessionParticipant->patient_name,
                $row->treatmentSessionParticipant->treatmentSession->practitioner_name,
                $row->amount,
                $row->treatmentSessionParticipant->practitioner_share,
                ...($this->includeClinicShare ? [$row->treatmentSessionParticipant->clinic_share] : []),
                $row->refunds->sum('amount'),
                $row->status->value,
            ],
            'expenses' => [
                $row->id,
                $row->occurred_at->format('Y-m-d H:i'),
                $row->category->name,
                $row->cashbox->name,
                $row->amount,
                $row->description,
                $row->affects_weekly_net ? 'نعم' : 'لا',
                $row->creator?->name,
            ],
            'practitioners' => [
                $row->earned_at->format('Y-m-d H:i'),
                $row->practitioner->name,
                $row->treatmentSessionParticipant->patient_name,
                $row->treatmentSessionParticipant->treatmentSession->treatment_type_name,
                $row->gross_amount,
                $row->practitioner_percentage,
                $row->amount,
            ],
            'cashbox' => [$row->id, $row->occurred_at->format('Y-m-d H:i'), $row->cashbox->name, $row->type->label(), $row->direction->value === 'in' ? 'داخل' : 'خارج', $row->amount, PresentationLabels::value('description', $row->description), $row->creator?->name],
            'weekly_close' => [$row->financialWeek->starts_on->format('Y-m-d').' — '.$row->financialWeek->ends_on->format('Y-m-d'), $row->paid_sessions_count, $row->collections, $row->refunds, $row->practitioner_shares, $row->expenses, $row->adjustments, $row->calculated_net, $row->transferred_surplus, $row->deficit_covered, $row->closed_at->format('Y-m-d H:i')],
            'room_utilization' => [$row->name, $row->appointments_count, $row->completed_sessions_count, $row->booked_minutes ?? 0, round(($row->booked_minutes ?? 0) / 60, 2), $this->utilizationRate($row->booked_minutes ?? 0), $this->percentage($row->cancelled_count, $row->appointments_count), $this->percentage($row->no_show_count, $row->appointments_count), $row->room_revenue, $row->completed_sessions_count ? round($row->room_revenue / $row->completed_sessions_count) : 0, $this->trend($row->earlier_sessions_count, $row->recent_sessions_count)],
            'specialty_treatment' => [$row->specialty?->name, $row->name, $row->patients_count, $row->sessions_count, $row->treatment_revenue, $row->sessions_count ? round($row->treatment_revenue / $row->sessions_count) : 0, $row->new_patients_count, max(0, $row->patients_count - $row->new_patients_count), $row->practitioner_distribution, $this->trend($row->earlier_sessions_count, $row->recent_sessions_count), $row->is_group ? 'جماعية' : 'فردية'],
            'refunds_adjustments' => [$row->id, $row->occurred_at->format('Y-m-d H:i'), $row->type->label(), $row->practitioner?->name, $row->financialWeek->starts_on->format('Y-m-d'), $row->amount, $row->reason, $row->creator?->name],
            'audit' => [
                $row->created_at->format('Y-m-d'),
                $row->created_at->format('H:i:s'),
                $row->user?->name ?: 'النظام',
                PresentationLabels::auditAction($row->action),
                PresentationLabels::auditEntities()[$row->auditable_type] ?? 'سجل مالي',
                PresentationLabels::entityDescription($row),
                $this->auditAmount($row),
                $this->auditChanges($row),
            ],
            default => [],
        };
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && preg_match('/^[\s\p{Z}\x{FEFF}]*[=+\-@]/u', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private function patientsQuery(): Builder
    {
        return Patient::query()
            ->with('referringPractitioner:id,name')
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('created_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('created_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('source'), fn (Builder $query, string $value) => $query->where('source', $value))
            ->when($this->filter('practitioner_id'), fn (Builder $query, mixed $value) => $query->where('current_practitioner_id', $value))
            ->when($this->filter('search'), function (Builder $query, string $value): void {
                $query->where(fn (Builder $nested) => $nested->where('medical_file_number', $value)->orWhere('phone', $value)->orWhere('full_name', 'like', $value.'%'));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function appointmentsQuery(): Builder
    {
        return Appointment::query()
            ->with(['practitioner:id,name', 'room:id,name', 'treatmentType:id,name', 'participants.patient:id,full_name'])
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('starts_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('starts_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('practitioner_id'), fn (Builder $query, mixed $value) => $query->where('practitioner_id', $value))
            ->when($this->filter('room_id'), fn (Builder $query, mixed $value) => $query->where('room_id', $value))
            ->when($this->filter('treatment_type_id'), fn (Builder $query, mixed $value) => $query->where('treatment_type_id', $value))
            ->when($this->filter('status'), fn (Builder $query, string $value) => $query->where('status', $value))
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    private function sessionsQuery(): Builder
    {
        return TreatmentSessionParticipant::query()
            ->with(['patient:id,full_name', 'treatmentSession'])
            ->whereHas('treatmentSession', function (Builder $query): void {
                $query
                    ->when($this->filter('from'), fn (Builder $nested, string $value) => $nested->where('started_at', '>=', $value.' 00:00:00'))
                    ->when($this->filter('to'), fn (Builder $nested, string $value) => $nested->where('started_at', '<=', $value.' 23:59:59'))
                    ->when($this->filter('practitioner_id'), fn (Builder $nested, mixed $value) => $nested->where('practitioner_id', $value))
                    ->when($this->filter('room_id'), fn (Builder $nested, mixed $value) => $nested->where('room_id', $value))
                    ->when($this->filter('treatment_type_id'), fn (Builder $nested, mixed $value) => $nested->where('treatment_type_id', $value));
            })
            ->orderByDesc('id');
    }

    private function financialQuery(): Builder
    {
        return Payment::query()
            ->with(['refunds', 'treatmentSessionParticipant.treatmentSession'])
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('paid_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('paid_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('practitioner_id'), fn (Builder $query, mixed $value) => $query->whereHas('treatmentSessionParticipant.treatmentSession', fn (Builder $nested) => $nested->where('practitioner_id', $value)))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    private function expensesQuery(): Builder
    {
        return Expense::query()
            ->with(['category:id,name', 'cashbox:id,name', 'creator:id,name'])
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('occurred_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('occurred_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('category_id'), fn (Builder $query, mixed $value) => $query->where('expense_category_id', $value))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    private function practitionerEarningsQuery(): Builder
    {
        return PractitionerEarning::query()
            ->with(['practitioner:id,name', 'treatmentSessionParticipant.treatmentSession'])
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('earned_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('earned_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('practitioner_id'), fn (Builder $query, mixed $value) => $query->where('practitioner_id', $value))
            ->orderByDesc('earned_at')
            ->orderByDesc('id');
    }

    private function cashboxQuery(): Builder
    {
        return CashboxTransaction::query()
            ->with(['cashbox:id,name', 'creator:id,name'])
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('occurred_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('occurred_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('status'), fn (Builder $query, string $value) => $query->where('type', $value))
            ->latest('occurred_at');
    }

    private function weeklyClosingQuery(): Builder
    {
        return WeeklyClosing::query()->with(['financialWeek', 'closedBy:id,name'])->latest('closed_at');
    }

    private function roomUtilizationQuery(): Builder
    {
        return app(ReportAnalyticsService::class)->rooms($this->filter('from'), $this->filter('to'));
    }

    private function treatmentPerformanceQuery(): Builder
    {
        return app(ReportAnalyticsService::class)->treatments($this->filter('from'), $this->filter('to'));
    }

    private function adjustmentsQuery(): Builder
    {
        return FinancialAdjustment::query()
            ->with(['practitioner:id,name', 'financialWeek:id,starts_on,ends_on', 'creator:id,name'])
            ->when($this->filter('from'), fn (Builder $query, string $value) => $query->where('occurred_at', '>=', $value.' 00:00:00'))
            ->when($this->filter('to'), fn (Builder $query, string $value) => $query->where('occurred_at', '<=', $value.' 23:59:59'))
            ->when($this->filter('practitioner_id'), fn (Builder $query, mixed $value) => $query->where('practitioner_id', $value))
            ->latest('occurred_at');
    }

    private function auditQuery(): Builder
    {
        return app(AuditLogQuery::class)->build($this->filters)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function auditAmount(FinancialAuditLog $log): int|float|string|null
    {
        return $log->after['amount'] ?? $log->after['final_amount'] ?? $log->before['amount'] ?? null;
    }

    private function auditChanges(FinancialAuditLog $log): string
    {
        return collect($log->after ?? [])
            ->map(function (mixed $value, string $field): ?string {
                $label = PresentationLabels::field($field);

                return $label === null || is_array($value) ? null : $label.': '.PresentationLabels::value($field, $value);
            })
            ->filter()
            ->implode(' | ');
    }

    private function filter(string $key): mixed
    {
        $value = $this->filters[$key] ?? null;

        return filled($value) ? $value : null;
    }

    private function percentage(int|float $part, int|float $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
    }

    private function utilizationRate(int|float $minutes): float
    {
        $from = CarbonImmutable::parse($this->filter('from') ?: now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($this->filter('to') ?: now())->startOfDay();
        $capacityMinutes = max(1, $from->diffInDays($to) + 1) * 8 * 60;

        return min(100, round(($minutes / $capacityMinutes) * 100, 1));
    }

    private function trend(int|float $earlier, int|float $recent): string
    {
        if ($earlier <= 0) {
            return $recent > 0 ? 'جديد' : 'مستقر';
        }

        $change = round((($recent - $earlier) / $earlier) * 100, 1);

        return ($change > 0 ? '+' : '').$change.'%';
    }
}
