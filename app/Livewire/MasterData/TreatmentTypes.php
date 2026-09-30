<?php

namespace App\Livewire\MasterData;

use App\Models\Specialty;
use App\Models\TreatmentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('أنواع العلاج والجلسات')]
class TreatmentTypes extends Component
{
    use WithPagination;

    #[Url(as: 'بحث')]
    public string $search = '';

    public ?int $editingId = null;

    public ?int $specialtyId = null;

    public string $name = '';

    public string $description = '';

    public bool $isGroup = false;

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('master_data.manage');
    }

    #[Computed]
    public function treatmentTypes(): LengthAwarePaginator
    {
        return TreatmentType::query()
            ->with('specialty:id,name')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', trim($this->search).'%'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);
    }

    #[Computed]
    public function specialties(): Collection
    {
        return Specialty::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $treatmentTypeId): void
    {
        Gate::authorize('master_data.manage');

        $treatmentType = TreatmentType::query()->findOrFail($treatmentTypeId);
        $this->editingId = $treatmentType->id;
        $this->specialtyId = $treatmentType->specialty_id;
        $this->name = $treatmentType->name;
        $this->description = $treatmentType->description ?? '';
        $this->isGroup = $treatmentType->is_group;
        $this->isActive = $treatmentType->is_active;
    }

    public function save(): void
    {
        Gate::authorize('master_data.manage');

        $validated = $this->validate([
            'specialtyId' => ['nullable', 'integer', 'exists:specialties,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('treatment_types', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'isGroup' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        TreatmentType::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'specialty_id' => $validated['specialtyId'],
                'name' => trim($validated['name']),
                'description' => filled($validated['description']) ? trim($validated['description']) : null,
                'is_group' => $validated['isGroup'],
                'is_active' => $validated['isActive'],
            ],
        );

        $this->cancelEditing();
        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ نوع الجلسة بنجاح.');
    }

    public function toggle(int $treatmentTypeId): void
    {
        Gate::authorize('master_data.manage');
        $treatmentType = TreatmentType::query()->findOrFail($treatmentTypeId);
        $treatmentType->update(['is_active' => ! $treatmentType->is_active]);
    }

    public function delete(int $treatmentTypeId): void
    {
        Gate::authorize('master_data.manage');

        TreatmentType::query()->findOrFail($treatmentTypeId)->delete();

        if ($this->editingId === $treatmentTypeId) {
            $this->cancelEditing();
        }

        $this->dispatch('app-toast', type: 'success', message: 'تم حذف نوع العلاج مع الاحتفاظ بسجله التاريخي.');
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingId', 'specialtyId', 'name', 'description', 'isGroup']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.master-data.treatment-types');
    }
}
