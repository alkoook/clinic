<?php

namespace App\Enums;

enum FinancialWeekStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return $this === self::Open ? 'مفتوح' : 'مغلق';
    }
}
