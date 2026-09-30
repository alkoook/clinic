<?php

namespace App\Livewire\Finance;

use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Services\CashboxService;
use App\Services\CashboxTransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
#[Title('الصناديق والحركات')]
class Cashboxes extends Component
{
    use WithPagination;

    #[Url(as: 'الصندوق')]
    public ?int $filterCashboxId = null;

    public ?int $manualCashboxId = null;

    public string $manualDirection = 'in';

    public int|string $manualAmount = '';

    public string $manualDescription = '';

    public string $manualToken = '';

    public ?int $fromCashboxId = null;

    public ?int $toCashboxId = null;

    public int|string $transferAmount = '';

    public string $transferReason = '';

    public string $transferToken = '';

    public function mount(): void
    {
        Gate::authorize('cashboxes.manage');
        $cashboxes = Cashbox::query()->orderBy('id')->get();
        $this->manualCashboxId = $cashboxes->first()?->id;
        $this->fromCashboxId = $cashboxes->first()?->id;
        $this->toCashboxId = $cashboxes->skip(1)->first()?->id;
        $this->manualToken = (string) Str::uuid();
        $this->transferToken = (string) Str::uuid();
    }

    #[Computed]
    public function cashboxes(): Collection
    {
        return Cashbox::query()
            ->select('cashboxes.*')
            ->selectSub(
                CashboxTransaction::query()
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)")
                    ->whereColumn('cashbox_id', 'cashboxes.id'),
                'balance',
            )
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return CashboxTransaction::query()
            ->with(['cashbox:id,name,type', 'creator:id,name'])
            ->when($this->filterCashboxId, fn ($query) => $query->where('cashbox_id', $this->filterCashboxId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function updatedFilterCashboxId(): void
    {
        $this->resetPage();
    }

    public function recordManual(CashboxService $cashboxService): void
    {
        Gate::authorize('cashboxes.manage');
        $validated = $this->validate([
            'manualCashboxId' => ['required', 'integer', Rule::exists('cashboxes', 'id')->where('is_active', true)],
            'manualDirection' => ['required', Rule::in(['in', 'out'])],
            'manualAmount' => ['required', 'integer', 'min:1'],
            'manualDescription' => ['nullable', 'string', 'max:1000'],
            'manualToken' => ['required', 'uuid'],
        ]);
        $cashbox = Cashbox::query()->findOrFail($validated['manualCashboxId']);

        if ($validated['manualDirection'] === 'in') {
            $cashboxService->deposit($cashbox, (int) $validated['manualAmount'], filled($validated['manualDescription']) ? trim($validated['manualDescription']) : null, auth()->user(), $validated['manualToken']);
        } else {
            $cashboxService->withdraw($cashbox, (int) $validated['manualAmount'], filled($validated['manualDescription']) ? trim($validated['manualDescription']) : null, auth()->user(), $validated['manualToken']);
        }

        $this->reset(['manualAmount', 'manualDescription']);
        $this->manualToken = (string) Str::uuid();
        $this->dispatch('app-toast', type: 'success', message: 'تم تسجيل حركة الصندوق.');
    }

    public function transfer(CashboxTransferService $cashboxTransferService): void
    {
        Gate::authorize('cashboxes.manage');
        $validated = $this->validate([
            'fromCashboxId' => ['required', 'integer', Rule::exists('cashboxes', 'id')->where('is_active', true), 'different:toCashboxId'],
            'toCashboxId' => ['required', 'integer', Rule::exists('cashboxes', 'id')->where('is_active', true)],
            'transferAmount' => ['required', 'integer', 'min:1'],
            'transferReason' => ['nullable', 'string', 'max:1000'],
            'transferToken' => ['required', 'uuid'],
        ]);

        $cashboxTransferService->transfer(
            Cashbox::query()->findOrFail($validated['fromCashboxId']),
            Cashbox::query()->findOrFail($validated['toCashboxId']),
            (int) $validated['transferAmount'],
            filled($validated['transferReason']) ? trim($validated['transferReason']) : null,
            auth()->user(),
            $validated['transferToken'],
        );

        $this->reset(['transferAmount', 'transferReason']);
        $this->transferToken = (string) Str::uuid();
        $this->dispatch('app-toast', type: 'success', message: 'تم التحويل بين الصندوقين بحركتين مترابطتين.');
    }

    public function render()
    {
        return view('livewire.finance.cashboxes');
    }
}
