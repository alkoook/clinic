<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';

    public function label(): string
    {
        return 'نقدًا';
    }
}
