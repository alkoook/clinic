<?php

namespace App\Livewire\Appointments;

use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Room;
use App\Models\TreatmentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('تقويم المواعيد')]
class Calendar extends Component
{
    #[Url(as: 'عرض')]
    public string $viewMode = 'week';

    #[Url(as: 'التاريخ')]
    public string $focusDate = '';

    #[Url(as: 'الطبيب')]
    public ?int $practitionerId = null;

    #[Url(as: 'الغرفة')]
    public ?int $roomId = null;

    #[Url(as: 'العلاج')]
    public ?int $treatmentTypeId = null;

    #[Url(as: 'الحالة')]
    public string $status = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user->can('appointments.manage') || $user->can('appointments.view_own'), 403);
        $this->focusDate = $this->focusDate ?: now()->format('Y-m-d');
        if ($user->hasRole('practitioner')) {
            $this->practitionerId = $user->practitioner?->id;
        }
    }

    #[Computed]
    public function range(): array
    {
        $focus = CarbonImmutable::parse($this->focusDate);
        if ($this->viewMode === 'day') {
            return [$focus->startOfDay(), $focus->endOfDay()];
        }

        return [$focus->startOfWeek(CarbonImmutable::SATURDAY)->startOfDay(), $focus->startOfWeek(CarbonImmutable::SATURDAY)->addDays(6)->endOfDay()];
    }

    #[Computed]
    public function appointments(): Collection
    {
        [$start, $end] = $this->range;

        return Appointment::query()
            ->with(['practitioner:id,name', 'room:id,name', 'treatmentType:id,name', 'participants.patient:id,full_name'])
            ->whereBetween('starts_at', [$start, $end])
            ->when(auth()->user()->hasRole('practitioner'), fn (Builder $query) => $query->where('practitioner_id', auth()->user()->practitioner?->id))
            ->when($this->practitionerId, fn (Builder $query) => $query->where('practitioner_id', $this->practitionerId))
            ->when($this->roomId, fn (Builder $query) => $query->where('room_id', $this->roomId))
            ->when($this->treatmentTypeId, fn (Builder $query) => $query->where('treatment_type_id', $this->treatmentTypeId))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function practitioners(): Collection
    {
        return Practitioner::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function treatmentTypes(): Collection
    {
        return TreatmentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function move(int $direction): void
    {
        $days = $this->viewMode === 'day' ? 1 : 7;
        $this->focusDate = CarbonImmutable::parse($this->focusDate)->addDays($direction * $days)->format('Y-m-d');
    }

    public function today(): void
    {
        $this->focusDate = now()->format('Y-m-d');
    }

    public function render()
    {
        return view('livewire.appointments.calendar');
    }
}
