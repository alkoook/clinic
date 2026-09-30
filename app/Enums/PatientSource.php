<?php

namespace App\Enums;

enum PatientSource: string
{
    case Clinic = 'clinic';
    case Practitioner = 'practitioner';

    public function label(): string
    {
        return $this === self::Clinic ? 'من طرف العيادة' : 'من طرف الطبيب / المعالج';
    }
}
