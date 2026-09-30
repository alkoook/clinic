<?php

namespace App\Livewire\Patients;

use App\Enums\PatientSource;
use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('المرضى')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'بحث')]
    public string $search = '';

    #[Url(as: 'المصدر')]
    public string $source = '';

    #[Url(as: 'الحالة')]
    public string $active = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Patient::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'source', 'active'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function patients(): LengthAwarePaginator
    {
        $user = auth()->user();
        $query = Patient::query()
            ->with(['referringPractitioner:id,name', 'currentPractitioner:id,name'])
            ->withCount(['attachments', 'appointmentParticipants']);

        if (! $user->can('patients.view')) {
            $practitionerId = $user->practitioner?->id;
            abort_unless($user->can('patients.view_own') && $practitionerId !== null, 403);
            $query->where(function (Builder $patientQuery) use ($practitionerId): void {
                $patientQuery
                    ->where('current_practitioner_id', $practitionerId)
                    ->orWhere('referring_practitioner_id', $practitionerId)
                    ->orWhereHas('appointmentParticipants.appointment', fn (Builder $appointmentQuery) => $appointmentQuery->where('practitioner_id', $practitionerId))
                    ->orWhereHas('treatmentSessionParticipants.treatmentSession', fn (Builder $sessionQuery) => $sessionQuery->where('practitioner_id', $practitionerId));
            });
        }

        return $query
            ->when($this->search !== '', function (Builder $patientQuery): void {
                $term = trim($this->search);
                $patientQuery->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('medical_file_number', $term)
                        ->orWhere('phone', $term)
                        ->orWhere('full_name', 'like', $term.'%');
                });
            })
            ->when($this->source !== '', fn (Builder $patientQuery) => $patientQuery->where('source', $this->source))
            ->when($this->active !== '', fn (Builder $patientQuery) => $patientQuery->where('is_active', $this->active === '1'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);
    }

    /** @return array<string, string> */
    public function sourceOptions(): array
    {
        return collect(PatientSource::cases())->mapWithKeys(fn (PatientSource $source) => [$source->value => $source->label()])->all();
    }

    public function render()
    {
        return view('livewire.patients.index');
    }
}
