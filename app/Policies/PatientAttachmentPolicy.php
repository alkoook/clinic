<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\User;

class PatientAttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('patient_attachments.view')
            && ($user->hasPermission('patients.view') || $user->hasPermission('patients.view_own'));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PatientAttachment $patientAttachment): bool
    {
        return app(PatientPolicy::class)->viewAttachments($user, $patientAttachment->patient);
    }

    public function preview(User $user, PatientAttachment $patientAttachment): bool
    {
        return $user->hasPermission('patient_attachments.preview')
            && app(PatientPolicy::class)->view($user, $patientAttachment->patient);
    }

    public function download(User $user, PatientAttachment $patientAttachment): bool
    {
        return $user->hasPermission('patient_attachments.download')
            && app(PatientPolicy::class)->view($user, $patientAttachment->patient);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, ?Patient $patient = null): bool
    {
        if (! $user->hasPermission('patient_attachments.upload')) {
            return false;
        }

        if ($patient === null) {
            return $user->hasPermission('patients.view') || $user->hasPermission('patients.view_own');
        }

        return app(PatientPolicy::class)->view($user, $patient);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PatientAttachment $patientAttachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PatientAttachment $patientAttachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PatientAttachment $patientAttachment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PatientAttachment $patientAttachment): bool
    {
        return false;
    }
}
