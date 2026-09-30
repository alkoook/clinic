<?php

namespace App\Livewire\Finance;

use App\Models\FinancialAgreement;
use App\Models\Practitioner;
use App\Services\FinancialAgreementService;
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
#[Title('الاتفاقات المالية')]
class Agreements extends Component
{
    use WithPagination;

    #[Url(as: 'الطبيب')]
    public ?int $filterPractitionerId = null;

    #[Url(as: 'الحالة')]
    public string $filterStatus = 'active';

    public ?int $editingId = null;

    public ?int $practitionerId = null;

    public int|string $practitionerPercentage = '';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public bool $isActive = true;

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('financial.agreements.manage');
        $this->effectiveFrom = now()->format('Y-m-d');
    }

    #[Computed]
    public function agreements(): LengthAwarePaginator
    {
        return FinancialAgreement::query()
            ->when($this->filterStatus === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->when($this->filterStatus === 'all', fn (Builder $query) => $query->withTrashed())
            ->when($this->filterStatus === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->filterStatus === 'ended', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('is_active', false)->orWhereDate('effective_to', '<', today())))
            ->with(['practitioner:id,name', 'creator:id,name'])
            ->when($this->filterPractitionerId, fn (Builder $query) => $query->where('practitioner_id', $this->filterPractitionerId))
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function updatedFilterPractitionerId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function delete(int $agreementId, FinancialAgreementService $agreementService): void
    {
        Gate::authorize('financial.agreements.manage');
        $agreementService->delete(FinancialAgreement::query()->findOrFail($agreementId), auth()->user());
        unset($this->agreements);
        $this->dispatch('app-toast', type: 'success', message: 'تمت أرشفة الاتفاق مع الحفاظ على كل المراجع المالية التاريخية.');
    }

    public function edit(int $agreementId): void
    {
        Gate::authorize('financial.agreements.manage');
        $agreement = FinancialAgreement::query()->findOrFail($agreementId);
        $this->editingId = $agreement->id;
        $this->practitionerId = $agreement->practitioner_id;
        $this->practitionerPercentage = (string) $agreement->practitioner_percentage;
        $this->effectiveFrom = $agreement->effective_from->format('Y-m-d');
        $this->effectiveTo = $agreement->effective_to?->format('Y-m-d') ?? '';
        $this->isActive = $agreement->is_active;
        $this->notes = $agreement->notes ?? '';
    }

    public function save(FinancialAgreementService $agreementService): void
    {
        Gate::authorize('financial.agreements.manage');
        $validated = $this->validate([
            'practitionerId' => ['required', 'integer', 'exists:practitioners,id'],
            'practitionerPercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'effectiveFrom' => ['required', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
            'isActive' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'practitioner_id' => $validated['practitionerId'],
            'practitioner_percentage' => $validated['practitionerPercentage'],
            'effective_from' => $validated['effectiveFrom'],
            'effective_to' => filled($validated['effectiveTo']) ? $validated['effectiveTo'] : null,
            'is_active' => $validated['isActive'],
            'notes' => filled($validated['notes']) ? trim($validated['notes']) : null,
        ];

        if ($this->editingId) {
            $agreementService->update(FinancialAgreement::query()->findOrFail($this->editingId), $data, auth()->user());
        } else {
            $agreementService->create($data, auth()->user());
        }

        $this->cancelEditing();
        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ الاتفاق مع الحفاظ على لقطات الجلسات السابقة.');
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingId', 'practitionerId', 'practitionerPercentage', 'effectiveTo', 'notes']);
        $this->effectiveFrom = now()->format('Y-m-d');
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.finance.agreements');
    }
}
