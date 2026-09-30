<?php

namespace App\Services;

use App\Models\Patient;

class PatientFileNumberService
{
    public function nextForPractitioner(int $practitionerId): string
    {
        $lastNumber = Patient::query()
            ->where('current_practitioner_id', $practitionerId)
            ->orderByRaw('CAST(medical_file_number AS INTEGER) DESC')
            ->value('medical_file_number');

        return (string) ((int) $lastNumber + 1);
    }
}
