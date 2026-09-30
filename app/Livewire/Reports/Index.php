<?php

namespace App\Livewire\Reports;

use App\Jobs\PrepareReportExport;
use App\Models\Appointment;
use App\Models\CashboxTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAdjustment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\ReportExport;
use App\Models\Room;
use App\Models\TreatmentSessionParticipant;
use App\Models\TreatmentType;
use App\Models\WeeklyClosing;
use App\Services\ReportAnalyticsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('التقارير')]
class Index extends Component
{
    use WithPagination;

    /** @var list<string> */
    private const OWN_REPORT_TYPES = ['appointments', 'sessions', 'practitioners'];

    /** @var list<string> */
    private const REPORT_TYPES = ['patients', 'appointments', 'sessions', 'financial', 'expenses', 'practitioners', 'cashbox', 'weekly_close', 'room_utilization', 'specialty_treatment', 'refunds_adjustments'];

    #[Url(as: 'نوع')]
    public string $reportType = 'patients';

    #[Url(as: 'من')]
    public string $dateFrom = '';

    #[Url(as: 'إلى')]
    public string $dateTo = '';

    #[Url(as: 'الطبيب')]
    public ?int $practitionerId = null;

    #[Url(as: 'الغرفة')]
    public ?int $roomId = null;

    #[Url(as: 'العلاج')]
    public ?int $treatmentTypeId = null;

    #[Url(as: 'التصنيف')]
    public ?int $categoryId = null;

    #[Url(as: 'المصدر')]
    public string $source = '';

    #[Url(as: 'الحالة')]
    public string $status = '';

    #[Url(as: 'بحث')]
    public string $search = '';

    public function mount(): void
    {
        $hasOwnReportScope = $this->hasOwnReportScope();
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $this->dateTo ?: now()->format('Y-m-d');
        if ($hasOwnReportScope) {
            $this->practitionerId = auth()->user()->practitioner->getKey();
            if (! in_array($this->reportType, self::OWN_REPORT_TYPES, true)) {
                $this->reportType = 'appointments';
            }
        }
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $this->authorizeReportType();

        return match ($this->reportType) {
            'appointments' => $this->appointmentsQuery()->paginate(20),
            'sessions' => $this->sessionsQuery()->paginate(20),
            'financial' => $this->financialQuery()->paginate(20),
            'expenses' => $this->expensesQuery()->paginate(20),
            'practitioners' => $this->practitionerEarningsQuery()->paginate(20),
            'cashbox' => $this->cashboxQuery()->paginate(20),
            'weekly_close' => $this->weeklyClosingQuery()->paginate(20),
            'room_utilization' => $this->roomUtilizationQuery()->paginate(20),
            'specialty_treatment' => $this->treatmentPerformanceQuery()->paginate(20),
            'refunds_adjustments' => $this->adjustmentsQuery()->paginate(20),
            default => $this->patientsQuery()->paginate(20),
        };
    }

    public function requestExport(): void
    {
        $this->authorizeReportType();
        $filters = array_filter([
            'type' => $this->reportType,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'practitioner_id' => $this->effectivePractitionerId(),
            'room_id' => $this->roomId,
            'treatment_type_id' => $this->treatmentTypeId,
            'category_id' => $this->categoryId,
            'source' => $this->source,
            'status' => $this->status,
            'search' => $this->search,
        ], fn ($value) => filled($value));

        $reportExport = ReportExport::query()->create([
            'requested_by' => auth()->id(),
            'type' => $this->reportType,
            'filters' => $filters,
            'status' => 'pending',
            'download_name' => $this->arabicExportName(),
        ]);

        PrepareReportExport::dispatch($reportExport->getKey())->onQueue('exports')->afterCommit();
        $this->dispatch('app-toast', type: 'success', message: 'جارٍ تجهيز التقرير في الخلفية. سنرسل إليك إشعارًا عندما يصبح جاهزًا.');
    }

    #[Computed]
    public function recentExports(): Collection
    {
        return ReportExport::query()->where('requested_by', auth()->id())->latest()->limit(5)->get();
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()
            ->when($this->hasOwnReportScope(), fn (Builder $query) => $query->whereKey($this->effectivePractitionerId()))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function treatmentTypes(): Collection
    {
        return TreatmentType::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get(['id', 'name']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['reportType', 'dateFrom', 'dateTo', 'practitionerId', 'roomId', 'treatmentTypeId', 'categoryId', 'source', 'status', 'search'], true)) {
            if ($this->hasOwnReportScope()) {
                if (! in_array($this->reportType, self::OWN_REPORT_TYPES, true)) {
                    $this->reportType = 'appointments';
                }

                $this->practitionerId = auth()->user()->practitioner->getKey();
            }

            $this->resetPage();
        }
    }

    public function render()
    {
        return view('livewire.reports.index');
    }

    private function patientsQuery(): Builder
    {
        $practitionerId = $this->effectivePractitionerId();

        return Patient::query()
            ->with('referringPractitioner:id,name')
            ->when($this->dateFrom, fn (Builder $query) => $query->where('created_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('created_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->source, fn (Builder $query) => $query->where('source', $this->source))
            ->when($practitionerId, fn (Builder $query) => $query->where('current_practitioner_id', $practitionerId))
            ->when($this->search, function (Builder $query): void {
                $term = trim($this->search);
                $query->where(fn (Builder $nested) => $nested->where('medical_file_number', $term)->orWhere('phone', $term)->orWhere('full_name', 'like', $term.'%'));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function appointmentsQuery(): Builder
    {
        $practitionerId = $this->effectivePractitionerId();

        return Appointment::query()
            ->with(['practitioner:id,name', 'room:id,name', 'treatmentType:id,name', 'participants.patient:id,full_name'])
            ->when($this->dateFrom, fn (Builder $query) => $query->where('starts_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('starts_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($practitionerId, fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
            ->when($this->roomId, fn (Builder $query) => $query->where('room_id', $this->roomId))
            ->when($this->treatmentTypeId, fn (Builder $query) => $query->where('treatment_type_id', $this->treatmentTypeId))
            ->when($this->status, fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    private function sessionsQuery(): Builder
    {
        $practitionerId = $this->effectivePractitionerId();

        return TreatmentSessionParticipant::query()
            ->with('treatmentSession')
            ->whereHas('treatmentSession', function (Builder $query) use ($practitionerId): void {
                $query
                    ->when($this->dateFrom, fn (Builder $nested) => $nested->where('started_at', '>=', $this->dateFrom.' 00:00:00'))
                    ->when($this->dateTo, fn (Builder $nested) => $nested->where('started_at', '<=', $this->dateTo.' 23:59:59'))
                    ->when($practitionerId, fn (Builder $nested) => $nested->where('practitioner_id', $practitionerId))
                    ->when($this->roomId, fn (Builder $nested) => $nested->where('room_id', $this->roomId))
                    ->when($this->treatmentTypeId, fn (Builder $nested) => $nested->where('treatment_type_id', $this->treatmentTypeId));
            })
            ->orderByDesc('id');
    }

    private function financialQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);
        $practitionerId = $this->effectivePractitionerId();

        return Payment::query()
            ->with(['refunds', 'treatmentSessionParticipant.treatmentSession'])
            ->when($this->dateFrom, fn (Builder $query) => $query->where('paid_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('paid_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($practitionerId, fn (Builder $query) => $query->whereHas('treatmentSessionParticipant.treatmentSession', fn (Builder $nested) => $nested->where('practitioner_id', $practitionerId)))
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    private function expensesQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return Expense::query()
            ->with(['category:id,name', 'cashbox:id,name', 'creator:id,name'])
            ->when($this->dateFrom, fn (Builder $query) => $query->where('occurred_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('occurred_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->categoryId, fn (Builder $query) => $query->where('expense_category_id', $this->categoryId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    private function practitionerEarningsQuery(): Builder
    {
        $practitionerId = $this->effectivePractitionerId();

        return PractitionerEarning::query()
            ->with(['practitioner:id,name', 'treatmentSessionParticipant.treatmentSession'])
            ->when($this->dateFrom, fn (Builder $query) => $query->where('earned_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('earned_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($practitionerId, fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
            ->orderByDesc('earned_at')
            ->orderByDesc('id');
    }

    private function cashboxQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return CashboxTransaction::query()
            ->with(['cashbox:id,name', 'creator:id,name'])
            ->when($this->dateFrom, fn (Builder $query) => $query->where('occurred_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('occurred_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->status, fn (Builder $query) => $query->where('type', $this->status))
            ->latest('occurred_at');
    }

    private function weeklyClosingQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return WeeklyClosing::query()->with(['financialWeek', 'closedBy:id,name'])->latest('closed_at');
    }

    private function roomUtilizationQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return app(ReportAnalyticsService::class)->rooms($this->dateFrom, $this->dateTo);
    }

    private function treatmentPerformanceQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return app(ReportAnalyticsService::class)->treatments($this->dateFrom, $this->dateTo);
    }

    private function adjustmentsQuery(): Builder
    {
        abort_unless(auth()->user()->can('reports.view'), 403);

        return FinancialAdjustment::query()
            ->with(['practitioner:id,name', 'financialWeek:id,starts_on,ends_on', 'creator:id,name', 'refund'])
            ->when($this->dateFrom, fn (Builder $query) => $query->where('occurred_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo, fn (Builder $query) => $query->where('occurred_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->effectivePractitionerId(), fn (Builder $query, int $id) => $query->where('practitioner_id', $id))
            ->latest('occurred_at');
    }

    private function effectivePractitionerId(): ?int
    {
        if ($this->hasOwnReportScope()) {
            return auth()->user()->practitioner->getKey();
        }

        return $this->practitionerId;
    }

    private function authorizeReportType(): void
    {
        $allowedTypes = $this->hasOwnReportScope() ? self::OWN_REPORT_TYPES : self::REPORT_TYPES;

        abort_unless(in_array($this->reportType, $allowedTypes, true), 403);
    }

    private function hasOwnReportScope(): bool
    {
        $user = auth()->user();

        if ($user->can('reports.view')) {
            return false;
        }

        abort_unless($user->can('reports.view_own') && $user->practitioner?->getKey() !== null, 403);

        return true;
    }

    private function arabicExportName(): string
    {
        $label = match ($this->reportType) {
            'appointments' => 'المواعيد',
            'sessions' => 'الجلسات',
            'financial' => 'المالية',
            'expenses' => 'المصروفات',
            'practitioners' => 'مستحقات_المعالجين',
            'cashbox' => 'حركات_الصندوق',
            'weekly_close' => 'الإغلاق_الأسبوعي',
            'room_utilization' => 'استخدام_الغرف',
            'specialty_treatment' => 'الاختصاصات_والعلاجات',
            'refunds_adjustments' => 'الاستردادات_والتعديلات',
            default => 'المرضى',
        };

        return "تقرير_{$label}_من_{$this->dateFrom}_إلى_{$this->dateTo}.xlsx";
    }
}
