<?php

namespace App\Livewire\Finance;

use App\Enums\FinancialWeekStatus;
use App\Exceptions\FinancialException;
use App\Models\FinancialWeek;
use App\Models\Practitioner;
use App\Services\FinancialAdjustmentService;
use App\Services\FinancialWeekService;
use App\Services\WeeklyClosingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('إغلاق الأسبوع المالي')]
class WeeklyClosing extends Component
{
    public ?int $financialWeekId = null;

    public string $closingToken = '';

    public bool $confirmed = false;

    public ?int $adjustmentPractitionerId = null;

    public int|string $adjustmentAmount = '';

    public string $adjustmentReason = '';

    public string $adjustmentToken = '';

    public string $reopenReason = '';

    public function mount(FinancialWeekService $financialWeekService): void
    {
        Gate::authorize('cashboxes.manage');
        $this->financialWeekId = $financialWeekService->current()->id;
        $this->closingToken = (string) Str::uuid();
        $this->adjustmentToken = (string) Str::uuid();
    }

    #[Computed]
    public function week(): FinancialWeek
    {
        return FinancialWeek::query()->with('closing')->findOrFail($this->financialWeekId);
    }

    #[Computed]
    public function weeks(): Collection
    {
        return FinancialWeek::query()
            ->with('closing')
            ->orderByDesc('starts_on')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function preview(): array
    {
        return app(WeeklyClosingService::class)->preview($this->week);
    }

    #[Computed]
    public function canCloseWeek(): bool
    {
        return $this->week->status === FinancialWeekStatus::Open;
    }

    public function openNew(FinancialWeekService $financialWeekService): void
    {
        Gate::authorize('cashboxes.manage');

        try {
            $week = $financialWeekService->openNew(auth()->user());
        } catch (FinancialException $exception) {
            $this->addError('financialWeekId', $exception->getMessage());

            return;
        }

        $this->financialWeekId = $week->getKey();
        unset($this->week, $this->preview, $this->weeks, $this->canCloseWeek);
        $this->dispatch('app-toast', type: 'success', message: 'تم فتح فترة مالية جديدة.');
    }

    public function reopen(WeeklyClosingService $weeklyClosingService): void
    {
        Gate::authorize('cashboxes.manage');

        try {
            $weeklyClosingService->reopen($this->week, auth()->user(), $this->reopenReason);
        } catch (FinancialException $exception) {
            $this->addError('financialWeekId', $exception->getMessage());

            return;
        }

        $this->reopenReason = '';
        unset($this->week, $this->preview, $this->weeks, $this->canCloseWeek);
        $this->dispatch('app-toast', type: 'success', message: 'تمت إعادة فتح الفترة المالية وتوثيق العملية.');
    }

    public function updatedFinancialWeekId(): void
    {
        $this->confirmed = false;
        unset($this->week, $this->preview, $this->canCloseWeek);
    }

    public function close(WeeklyClosingService $weeklyClosingService): void
    {
        Gate::authorize('cashboxes.manage');
        $validated = $this->validate([
            'financialWeekId' => ['required', 'integer', 'exists:financial_weeks,id'],
            'closingToken' => ['required', 'uuid'],
            'confirmed' => ['accepted'],
        ]);

        try {
            $closing = $weeklyClosingService->close(
                FinancialWeek::query()->findOrFail($validated['financialWeekId']),
                auth()->user(),
                $validated['closingToken'],
            );
        } catch (FinancialException $exception) {
            $this->addError('financialWeekId', $exception->getMessage());

            return;
        }

        $this->closingToken = (string) Str::uuid();
        $this->confirmed = false;
        unset($this->week, $this->preview, $this->weeks, $this->canCloseWeek);
        $this->dispatch('app-toast', type: 'success', message: 'تم إغلاق الأسبوع وتثبيت أرقامه وإنشاء تسويات الأطباء.');
        $this->dispatch('week-closed', closingId: $closing->id);
    }

    public function recordAdjustment(FinancialAdjustmentService $adjustmentService): void
    {
        Gate::authorize('cashboxes.manage');
        $validated = $this->validate([
            'financialWeekId' => ['required', 'integer', 'exists:financial_weeks,id'],
            'adjustmentPractitionerId' => ['nullable', 'integer', 'exists:practitioners,id'],
            'adjustmentAmount' => ['required', 'integer', 'not_in:0'],
            'adjustmentReason' => ['nullable', 'string', 'max:1000'],
            'adjustmentToken' => ['required', 'uuid'],
        ]);

        try {
            $adjustmentService->record(
                FinancialWeek::query()->findOrFail($validated['financialWeekId']),
                filled($validated['adjustmentPractitionerId'])
                    ? Practitioner::query()->findOrFail($validated['adjustmentPractitionerId'])
                    : null,
                (int) $validated['adjustmentAmount'],
                filled($validated['adjustmentReason']) ? trim($validated['adjustmentReason']) : null,
                auth()->user(),
                $validated['adjustmentToken'],
            );
        } catch (FinancialException $exception) {
            $this->addError('adjustmentAmount', $exception->getMessage());

            return;
        }

        $this->reset(['adjustmentPractitionerId', 'adjustmentAmount', 'adjustmentReason']);
        $this->adjustmentToken = (string) Str::uuid();
        unset($this->preview);
        $this->dispatch('app-toast', type: 'success', message: 'تم تسجيل التعديل المالي وتوثيقه دون تعديل أي حركة سابقة.');
    }

    public function render()
    {
        return view('livewire.finance.weekly-closing');
    }
}
