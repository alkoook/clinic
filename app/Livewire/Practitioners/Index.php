<?php

namespace App\Livewire\Practitioners;

use App\Models\Practitioner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('الأطباء والمعالجون')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'بحث')]
    public string $search = '';

    #[Url(as: 'الحالة')]
    public string $active = '';

    public function mount(): void
    {
        Gate::authorize('practitioners.manage');
    }

    #[Computed]
    public function practitioners(): LengthAwarePaginator
    {
        return Practitioner::query()
            ->with(['user:id,username,is_active', 'specialties:id,name'])
            ->withCount(['appointments', 'patients'])
            ->when($this->search !== '', function (Builder $query): void {
                $term = trim($this->search);
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', $term.'%')
                    ->orWhere('phone', $term)
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('username', $term)));
            })
            ->when($this->active !== '', fn (Builder $query) => $query->where('is_active', $this->active === '1'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'active'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $practitionerId): void
    {
        Gate::authorize('practitioners.manage');

        DB::transaction(function () use ($practitionerId): void {
            $practitioner = Practitioner::query()->with('user')->lockForUpdate()->findOrFail($practitionerId);
            $isActive = ! $practitioner->is_active;
            $practitioner->update(['is_active' => $isActive]);
            $practitioner->user->update(['is_active' => $isActive]);
        });
    }

    public function render()
    {
        return view('livewire.practitioners.index');
    }
}
