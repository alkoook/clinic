<?php

namespace App\Enums;

enum AppointmentKind: string
{
    case Individual = 'individual';
    case Group = 'group';

    public function label(): string
    {
        return $this === self::Individual ? 'فردية' : 'جماعية';
    }
}
