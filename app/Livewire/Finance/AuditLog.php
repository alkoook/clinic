<?php

namespace App\Livewire\Finance;

use App\Jobs\PrepareReportExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\AuditLogQuery;
use App\Support\PresentationLabels;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('سجل العمليات المالية')]
class AuditLog extends Component
{
    use WithPagination;

    #[Url(as: 'العملية')]
    public string $action = '';

    #[Url(as: 'المنفذ')]
    public ?int $userId = null;

    #[Url(as: 'الكيان')]
    public string $entityType = '';

    #[Url(as: 'أدنى')]
    public int|string $amountMin = '';

    #[Url(as: 'أقصى')]
    public int|string $amountMax = '';

    #[Url(as: 'من')]
    public string $dateFrom = '';

    #[Url(as: 'إلى')]
    public string $dateTo = '';

    public function mount(): void
    {
        Gate::authorize('audit.view');
        $this->dateFrom = $this->dateFrom ?: now()->subMonth()->format('Y-m-d');
        $this->dateTo = $this->dateTo ?: now()->format('Y-m-d');
    }

    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['user:id,name,username', 'auditable'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /** @return array{total: int, payments: int, refunds: int, expenses: int, other: int} */
    #[Computed]
    public function summary(): array
    {
        $counts = $this->filteredQuery()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN action = 'payment.created' THEN 1 ELSE 0 END) as payments")
            ->selectRaw("SUM(CASE WHEN action = 'refund.created' THEN 1 ELSE 0 END) as refunds")
            ->selectRaw("SUM(CASE WHEN action = 'expense.created' THEN 1 ELSE 0 END) as expenses")
            ->first();
        $total = (int) $counts?->getAttribute('total');
        $payments = (int) $counts?->getAttribute('payments');
        $refunds = (int) $counts?->getAttribute('refunds');
        $expenses = (int) $counts?->getAttribute('expenses');

        return compact('total', 'payments', 'refunds', 'expenses') + ['other' => $total - $payments - $refunds - $expenses];
    }

    #[Computed]
    public function users(): Collection
    {
        return User::query()->whereHas('financialAuditLogs')->orderBy('name')->get(['id', 'name']);
    }

    /** @return array<string, string> */
    public function actionOptions(): array
    {
        return PresentationLabels::auditActions();
    }

    /** @return array<string, string> */
    public function entityOptions(): array
    {
        return PresentationLabels::auditEntities();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function requestExport(): void
    {
        Gate::authorize('audit.view');
        $filters = array_filter($this->filters(), fn (mixed $value): bool => filled($value));
        $export = ReportExport::query()->create([
            'requested_by' => auth()->id(),
            'type' => 'audit',
            'filters' => $filters,
            'status' => 'pending',
            'download_name' => 'تقرير_سجل_التدقيق_من_'.$this->dateFrom.'_إلى_'.$this->dateTo.'.xlsx',
        ]);

        PrepareReportExport::dispatch($export->getKey())->onQueue('exports')->afterCommit();
        $this->dispatch('app-toast', type: 'success', message: 'جارٍ تجهيز تقرير سجل التدقيق في الخلفية. سيصلك إشعار عند جاهزيته.');
    }

    public function render()
    {
        return view('livewire.finance.audit-log');
    }

    private function filteredQuery(): Builder
    {
        return app(AuditLogQuery::class)->build($this->filters());
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return [
            'action' => $this->action,
            'user_id' => $this->userId,
            'entity_type' => $this->entityType,
            'amount_min' => $this->amountMin,
            'amount_max' => $this->amountMax,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ];
    }
}
