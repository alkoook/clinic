<?php

namespace App\Livewire\Availability;

use App\Models\Practitioner;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('التوفر والدوام')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('practitioners.manage') || auth()->user()->can('appointments.manage'), 403);
    }

    public function render(): View
    {
        return view('livewire.availability.index', [
            'practitioners' => Practitioner::query()->with('specialties:id,name')->withCount('availabilities')->orderByDesc('is_active')->orderBy('name')->orderBy('id')->paginate(15),
        ]);
    }
}
