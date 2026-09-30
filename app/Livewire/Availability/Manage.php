<?php

namespace App\Livewire\Availability;

use App\Enums\AvailabilityExceptionType;
use App\Models\Practitioner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Manage extends Component
{
    public Practitioner $practitioner;

    public int $dayOfWeek = 0;

    public string $startTime = '09:00';

    public string $endTime = '10:00';

    public string $effectiveFrom = '';

    public string $effectiveTo = '';

    public string $exceptionDate = '';

    public string $exceptionType = 'unavailable';

    public string $exceptionStartTime = '';

    public string $exceptionEndTime = '';

    public string $exceptionReason = '';

    public function mount(Practitioner $practitioner): void
    {
        abort_unless($this->canManage($practitioner) || auth()->user()->can('appointments.manage'), 403);
        $this->practitioner = $practitioner;
        $this->exceptionDate = now()->format('Y-m-d');
    }

    #[Computed]
    public function periods(): Collection
    {
        return $this->practitioner->availabilities()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    #[Computed]
    public function exceptions(): Collection
    {
        return $this->practitioner->availabilityExceptions()
            ->where('date', '>=', now()->subMonth()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    public function addPeriod(): void
    {
        $this->authorizeAccess();
        $validated = $this->validate([
            'dayOfWeek' => ['required', 'integer', 'between:0,6'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i', 'after:startTime'],
            'effectiveFrom' => ['nullable', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
        ]);

        $overlapExists = $this->practitioner->availabilities()
            ->where('day_of_week', $validated['dayOfWeek'])
            ->where('is_active', true)
            ->where('start_time', '<', $validated['endTime'])
            ->where('end_time', '>', $validated['startTime'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages(['startTime' => 'تتداخل هذه الفترة مع فترة دوام موجودة.']);
        }

        $this->practitioner->availabilities()->create([
            'day_of_week' => $validated['dayOfWeek'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'effective_from' => filled($validated['effectiveFrom']) ? $validated['effectiveFrom'] : null,
            'effective_to' => filled($validated['effectiveTo']) ? $validated['effectiveTo'] : null,
            'is_active' => true,
        ]);

        $this->reset(['effectiveFrom', 'effectiveTo']);
        $this->dispatch('app-toast', type: 'success', message: 'تمت إضافة فترة الدوام.');
    }

    public function removePeriod(int $periodId): void
    {
        $this->authorizeAccess();
        $this->practitioner->availabilities()->findOrFail($periodId)->delete();
    }

    public function addException(): void
    {
        $this->authorizeAccess();
        $validated = $this->validate([
            'exceptionDate' => ['required', 'date'],
            'exceptionType' => ['required', Rule::enum(AvailabilityExceptionType::class)],
            'exceptionStartTime' => [Rule::requiredIf($this->exceptionType === AvailabilityExceptionType::Available->value), 'nullable', 'date_format:H:i'],
            'exceptionEndTime' => [Rule::requiredIf($this->exceptionType === AvailabilityExceptionType::Available->value), 'nullable', 'date_format:H:i', 'after:exceptionStartTime'],
            'exceptionReason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->practitioner->availabilityExceptions()->create([
            'date' => $validated['exceptionDate'],
            'type' => $validated['exceptionType'],
            'start_time' => filled($validated['exceptionStartTime']) ? $validated['exceptionStartTime'] : null,
            'end_time' => filled($validated['exceptionEndTime']) ? $validated['exceptionEndTime'] : null,
            'reason' => filled($validated['exceptionReason']) ? trim($validated['exceptionReason']) : null,
        ]);

        $this->reset(['exceptionStartTime', 'exceptionEndTime', 'exceptionReason']);
        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ الاستثناء.');
    }

    public function removeException(int $exceptionId): void
    {
        $this->authorizeAccess();
        $this->practitioner->availabilityExceptions()->findOrFail($exceptionId)->delete();
    }

    /** @return array<int, string> */
    public function weekdayLabels(): array
    {
        return [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless($this->canManage($this->practitioner), 403);
    }

    #[Computed]
    public function editable(): bool
    {
        return $this->canManage($this->practitioner);
    }

    private function canManage(Practitioner $practitioner): bool
    {
        $user = auth()->user();

        return $user->can('practitioners.manage')
            || ($user->can('practitioner.availability.manage_own') && $user->practitioner?->is($practitioner));
    }

    public function render()
    {
        return view('livewire.availability.manage')->title('أوقات توفر '.$this->practitioner->name);
    }
}
