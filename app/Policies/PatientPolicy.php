<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PatientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasPermission('patients.view')) {
            return true;
        }

        return $user->hasPermission('patients.view_own') && $user->practitioner()->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Patient $patient): bool
    {
        if ($user->hasPermission('patients.view')) {
            return true;
        }

        if (! $user->hasPermission('patients.view_own')) {
            return false;
        }

        $practitionerId = $user->practitioner?->getKey();

        if ($practitionerId === null) {
            return false;
        }

        if (in_array($practitionerId, [
            $patient->current_practitioner_id,
            $patient->referring_practitioner_id,
        ], true)) {
            return true;
        }

        return $patient->appointmentParticipants()
            ->whereHas('appointment', fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
            ->exists()
            || $patient->treatmentSessionParticipants()
                ->whereHas('treatmentSession', fn (Builder $query) => $query->where('practitioner_id', $practitionerId))
                ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('patients.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patients.update');
    }

    public function viewAttachments(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patient_attachments.view') && $this->view($user, $patient);
    }

    public function uploadAttachment(User $user, Patient $patient): bool
    {
        return $user->hasPermission('patient_attachments.upload') && $this->view($user, $patient);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Patient $patient): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        return false;
    }
}
