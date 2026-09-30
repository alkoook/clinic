<?php

namespace App\Livewire\Finance;

use App\Exceptions\FinancialException;
use App\Models\FinancialAdjustment;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\PractitionerSettlement;
use App\Services\PractitionerSettlementService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('مستحقات الأطباء والمعالجين')]
class Settlements extends Component
{
    use WithPagination;

    #[Url(as: 'الطبيب')]
    public ?int $practitionerId = null;

    #[Url(as: 'الحالة')]
    public string $status = '';

    public ?int $payingSettlementId = null;

    public int|string $paymentAmount = '';

    public string $paymentToken = '';

    public ?int $dailyPractitionerId = null;

    public int|string $dailyAmount = '';

    public string $dailyToken = '';

    public ?int $statementSettlementId = null;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->can('settlements.manage') || $user->hasRole('practitioner'), 403);
        if ($user->hasRole('practitioner')) {
            abort_unless($user->can('reports.view_own') && $user->practitioner?->id !== null, 403);
            $this->practitionerId = $user->practitioner->id;
        }
    }

    #[Computed]
    public function settlements(): LengthAwarePaginator
    {
        return PractitionerSettlement::query()
            ->with(['practitioner:id,name', 'financialWeek:id,starts_on,ends_on,status', 'payments'])
            ->when(auth()->user()->hasRole('practitioner'), fn (Builder $query) => $query->where('practitioner_id', auth()->user()->practitioner?->id))
            ->when($this->practitionerId, fn (Builder $query) => $query->where('practitioner_id', $this->practitionerId))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->latest('financial_week_id')
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['practitionerId', 'status'], true)) {
            $this->resetPage();
        }

        if ($property === 'practitionerId') {
            unset($this->currentUnpaidBalance);
        }
    }

    #[Computed]
    public function currentUnpaidBalance(): int
    {
        if ($this->practitionerId === null || ! auth()->user()->can('settlements.manage')) {
            return 0;
        }

        return app(PractitionerSettlementService::class)->accruedBalance($this->practitionerId);
    }

    public function viewStatement(int $settlementId): void
    {
        $settlement = PractitionerSettlement::query()->findOrFail($settlementId);
        $user = auth()->user();
        abort_unless(
            $user->can('settlements.manage')
            || ($user->can('reports.view_own') && $user->practitioner?->id === $settlement->practitioner_id),
            403,
        );
        $this->statementSettlementId = $settlement->id;
        unset($this->statementSettlement, $this->statementEarnings, $this->statementAdjustments);
    }

    #[Computed]
    public function statementSettlement(): ?PractitionerSettlement
    {
        if ($this->statementSettlementId === null) {
            return null;
        }

        $user = auth()->user();
        $practitionerId = $user->practitioner?->id;
        abort_unless(
            $user->can('settlements.manage')
            || ($user->can('reports.view_own') && $practitionerId !== null),
            403,
        );

        return PractitionerSettlement::query()
            ->with(['practitioner:id,name', 'financialWeek:id,starts_on,ends_on'])
            ->when(! $user->can('settlements.manage'), fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
            ->findOrFail($this->statementSettlementId);
    }

    #[Computed]
    public function statementEarnings(): Collection
    {
        if ($this->statementSettlement === null) {
            return new Collection;
        }

        return PractitionerEarning::query()
            ->with([
                'treatmentSessionParticipant:id,treatment_session_id,patient_name,original_price,discount_amount,final_amount,practitioner_percentage,practitioner_share,payment_status',
                'treatmentSessionParticipant.treatmentSession:id,treatment_type_id,started_at,treatment_type_name',
            ])
            ->where('practitioner_id', $this->statementSettlement->practitioner_id)
            ->where('financial_week_id', $this->statementSettlement->financial_week_id)
            ->orderBy('earned_at')
            ->get();
    }

    #[Computed]
    public function statementAdjustments(): Collection
    {
        if ($this->statementSettlement === null) {
            return new Collection;
        }

        return FinancialAdjustment::query()
            ->where('practitioner_id', $this->statementSettlement->practitioner_id)
            ->where('financial_week_id', $this->statementSettlement->financial_week_id)
            ->orderBy('occurred_at')
            ->get();
    }

    public function preparePayment(int $settlementId): void
    {
        Gate::authorize('settlements.manage');
        $settlement = PractitionerSettlement::query()->findOrFail($settlementId);
        abort_if($settlement->remaining_amount <= 0, 422, 'لا يوجد مبلغ متبقٍ لهذه التسوية.');
        $this->payingSettlementId = $settlement->id;
        $this->paymentAmount = $settlement->remaining_amount;
        $this->paymentToken = (string) Str::uuid();
    }

    public function pay(PractitionerSettlementService $settlementService): void
    {
        Gate::authorize('settlements.manage');
        $validated = $this->validate([
            'payingSettlementId' => ['required', 'integer', 'exists:practitioner_settlements,id'],
            'paymentAmount' => ['required', 'integer', 'min:1'],
            'paymentToken' => ['required', 'uuid'],
        ]);

        try {
            $settlement = PractitionerSettlement::query()->findOrFail($validated['payingSettlementId']);
            $settlementService->settleCurrentBalance((int) $settlement->practitioner_id, (int) $validated['paymentAmount'], auth()->user(), $validated['paymentToken']);
        } catch (FinancialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }

        $this->reset(['payingSettlementId', 'paymentAmount', 'paymentToken']);
        unset($this->currentUnpaidBalance, $this->settlements);
        $this->dispatch('app-toast', type: 'success', message: 'تم دفع المستحق وتسجيل خروجه من الصندوق الرئيسي.');
    }

    public function prepareDailySettlement(int $practitionerId, PractitionerSettlementService $settlementService): void
    {
        Gate::authorize('settlements.manage');
        $balance = $settlementService->accruedBalance($practitionerId);
        if ($balance <= 0) {
            $this->addError('operation', 'لا يوجد رصيد مستحق غير مدفوع لهذا المعالج حاليًا.');

            return;
        }
        $this->dailyPractitionerId = $practitionerId;
        $this->dailyAmount = $balance;
        $this->dailyToken = (string) Str::uuid();
    }

    public function settleDaily(PractitionerSettlementService $settlementService): void
    {
        Gate::authorize('settlements.manage');
        $validated = $this->validate([
            'dailyPractitionerId' => ['required', 'integer', 'exists:practitioners,id'],
            'dailyAmount' => ['required', 'integer', 'min:1'],
            'dailyToken' => ['required', 'uuid'],
        ]);
        try {
            $settlementService->settleCurrentBalance((int) $validated['dailyPractitionerId'], (int) $validated['dailyAmount'], auth()->user(), $validated['dailyToken']);
        } catch (FinancialException $exception) {
            $this->addError('operation', $exception->getMessage());

            return;
        }
        $this->reset(['dailyPractitionerId', 'dailyAmount', 'dailyToken']);
        unset($this->currentUnpaidBalance, $this->settlements);
        $this->dispatch('app-toast', type: 'success', message: 'تم تسجيل التسوية اليومية بنجاح.');
    }

    public function render()
    {
        return view('livewire.finance.settlements');
    }
}
