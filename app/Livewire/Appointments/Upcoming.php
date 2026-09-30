<?php

namespace App\Livewire\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Upcoming extends Component
{
    /** @return Collection<int, Appointment> */
    #[Computed]
    public function appointments(): Collection
    {
        $user = auth()->user();
        $now = now();
        $query = Appointment::query()
            ->with([
                'practitioner:id,name',
                'participants.patient:id,full_name',
            ])
            ->where('starts_at', '>', $now)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->whereHas('practitioner', function (Builder $practitionerQuery): void {
                $practitionerQuery->where('is_active', true);
            })
            ->whereNotExists(function (QueryBuilder $earlierQuery) use ($now): void {
                $earlierQuery
                    ->selectRaw('1')
                    ->from('appointments as earlier_appointments')
                    ->whereColumn('earlier_appointments.practitioner_id', 'appointments.practitioner_id')
                    ->where('earlier_appointments.starts_at', '>', $now)
                    ->where('earlier_appointments.status', '!=', AppointmentStatus::Cancelled->value)
                    ->where(function (QueryBuilder $orderQuery): void {
                        $orderQuery
                            ->whereColumn('earlier_appointments.starts_at', '<', 'appointments.starts_at')
                            ->orWhere(function (QueryBuilder $tieQuery): void {
                                $tieQuery
                                    ->whereColumn('earlier_appointments.starts_at', 'appointments.starts_at')
                                    ->whereColumn('earlier_appointments.id', '<', 'appointments.id');
                            });
                    });
            })
            ->orderBy('starts_at')
            ->orderBy('id');

        if ($user->hasRole('practitioner')) {
            $query->where('practitioner_id', $user->practitioner?->getKey() ?? 0);
        }

        return $query
            ->get()
            ->groupBy('practitioner_id')
            ->map(fn (Collection $appointments): Appointment => $appointments->first())
            ->values();
    }

    public function render()
    {
        return view('livewire.appointments.upcoming');
    }
}
