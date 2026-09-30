<?php

namespace App\Livewire\MasterData;

use App\Models\Room;
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
#[Title('إدارة الغرف')]
class Rooms extends Component
{
    use WithPagination;

    #[Url(as: 'بحث')]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public bool $isActive = true;

    public function mount(): void
    {
        abort_unless(Gate::any(['master_data.manage', 'rooms.view']), 403);
    }

    #[Computed]
    public function rooms(): LengthAwarePaginator
    {
        return Room::query()
            ->when($this->search !== '', function ($query): void {
                $term = trim($this->search).'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $roomId): void
    {
        Gate::authorize('master_data.manage');
        $room = Room::query()->findOrFail($roomId);
        $this->editingId = $room->id;
        $this->name = $room->name;
        $this->code = $room->code ?? '';
        $this->description = $room->description ?? '';
        $this->isActive = $room->is_active;
    }

    public function save(): void
    {
        Gate::authorize('master_data.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('rooms', 'name')->ignore($this->editingId)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'isActive' => ['boolean'],
        ]);

        Room::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => trim($validated['name']),
                'code' => filled($validated['code']) ? trim($validated['code']) : null,
                'description' => filled($validated['description']) ? trim($validated['description']) : null,
                'is_active' => $validated['isActive'],
            ],
        );

        $this->cancelEditing();
        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ الغرفة بنجاح.');
    }

    public function toggle(int $roomId): void
    {
        Gate::authorize('master_data.manage');
        $room = Room::query()->findOrFail($roomId);
        $room->update(['is_active' => ! $room->is_active]);
    }

    public function delete(int $roomId): void
    {
        Gate::authorize('master_data.manage');

        Room::query()->findOrFail($roomId)->delete();

        if ($this->editingId === $roomId) {
            $this->cancelEditing();
        }

        $this->dispatch('app-toast', type: 'success', message: 'تم حذف الغرفة مع الاحتفاظ بسجلها التاريخي.');
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.master-data.rooms');
    }
}
