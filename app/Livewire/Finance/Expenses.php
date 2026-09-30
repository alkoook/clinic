<?php

namespace App\Livewire\Finance;

use App\Models\Cashbox;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('المصاريف')]
class Expenses extends Component
{
    use WithPagination;

    #[Url(as: 'من')]
    public string $dateFrom = '';

    #[Url(as: 'إلى')]
    public string $dateTo = '';

    #[Url(as: 'التصنيف')]
    public ?int $filterCategoryId = null;

    public ?int $expenseCategoryId = null;

    public ?int $cashboxId = null;

    public int|string $amount = '';

    public string $occurredAt = '';

    public string $description = '';

    public bool $affectsWeeklyNet = true;

    public string $idempotencyKey = '';

    public string $newCategoryName = '';

    public function mount(): void
    {
        Gate::authorize('expenses.manage');
        $this->dateFrom = $this->dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $this->dateTo ?: now()->format('Y-m-d');
        $this->occurredAt = now()->format('Y-m-d\TH:i');
        $this->cashboxId = Cashbox::query()->where('type', 'main')->value('id');
        $this->idempotencyKey = (string) Str::uuid();
    }

    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        return Expense::query()
            ->with(['category:id,name', 'cashbox:id,name,type', 'creator:id,name'])
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->where('occurred_at', '>=', $this->dateFrom.' 00:00:00'))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->where('occurred_at', '<=', $this->dateTo.' 23:59:59'))
            ->when($this->filterCategoryId, fn (Builder $query) => $query->where('expense_category_id', $this->filterCategoryId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function categories(): Collection
    {
        return ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function cashboxes(): Collection
    {
        return Cashbox::query()->where('is_active', true)->orderBy('id')->get(['id', 'name', 'type']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['dateFrom', 'dateTo', 'filterCategoryId'], true)) {
            $this->resetPage();
        }
    }

    public function record(ExpenseService $expenseService): void
    {
        Gate::authorize('expenses.manage');
        $validated = $this->validate([
            'expenseCategoryId' => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'cashboxId' => ['required', 'integer', Rule::exists('cashboxes', 'id')->where('is_active', true)],
            'amount' => ['required', 'integer', 'min:1'],
            'occurredAt' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'affectsWeeklyNet' => ['boolean'],
            'idempotencyKey' => ['required', 'uuid'],
        ]);

        $expenseService->record(
            ExpenseCategory::query()->findOrFail($validated['expenseCategoryId']),
            Cashbox::query()->findOrFail($validated['cashboxId']),
            (int) $validated['amount'],
            filled($validated['description']) ? trim($validated['description']) : null,
            $validated['affectsWeeklyNet'],
            auth()->user(),
            $validated['idempotencyKey'],
            now()->parse($validated['occurredAt']),
        );

        $this->reset(['expenseCategoryId', 'amount', 'description']);
        $this->affectsWeeklyNet = true;
        $this->idempotencyKey = (string) Str::uuid();
        $this->dispatch('app-toast', type: 'success', message: 'تم تسجيل المصروف وحركة الصندوق.');
    }

    public function addCategory(): void
    {
        Gate::authorize('master_data.manage');
        $validated = $this->validate([
            'newCategoryName' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')],
        ]);
        ExpenseCategory::query()->create(['name' => trim($validated['newCategoryName']), 'is_active' => true]);
        $this->reset('newCategoryName');
    }

    public function render()
    {
        return view('livewire.finance.expenses');
    }
}
