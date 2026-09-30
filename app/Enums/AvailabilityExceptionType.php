<?php

namespace App\Enums;

enum AvailabilityExceptionType: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return $this === self::Available ? 'دوام استثنائي' : 'غير متاح';
    }
}
