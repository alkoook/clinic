<?php

namespace App\Livewire\MasterData;

use App\Models\Specialty;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('إدارة الاختصاصات')]
class Specialties extends Component
{
    use WithPagination;

    #[Url(as: 'بحث')]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('master_data.manage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function specialties(): LengthAwarePaginator
    {
        return Specialty::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', trim($this->search).'%'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);
    }

    public function edit(int $specialtyId): void
    {
        Gate::authorize('master_data.manage');

        $specialty = Specialty::query()->findOrFail($specialtyId);
        $this->editingId = $specialty->id;
        $this->name = $specialty->name;
        $this->description = $specialty->description ?? '';
        $this->isActive = $specialty->is_active;
    }

    public function save(): void
    {
        Gate::authorize('master_data.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('specialties', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ]);

        Specialty::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => trim($validated['name']),
                'description' => filled($validated['description']) ? trim($validated['description']) : null,
                'is_active' => $validated['isActive'],
            ],
        );

        $this->cancelEditing();
        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ الاختصاص بنجاح.');
    }

    public function toggle(int $specialtyId): void
    {
        Gate::authorize('master_data.manage');

        $specialty = Specialty::query()->findOrFail($specialtyId);
        $specialty->update(['is_active' => ! $specialty->is_active]);
    }

    public function delete(int $specialtyId): void
    {
        Gate::authorize('master_data.manage');

        Specialty::query()->findOrFail($specialtyId)->delete();

        if ($this->editingId === $specialtyId) {
            $this->cancelEditing();
        }

        $this->dispatch('app-toast', type: 'success', message: 'تم حذف الاختصاص مع الاحتفاظ بسجله التاريخي.');
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.master-data.specialties');
    }
}
