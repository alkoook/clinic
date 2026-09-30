<?php

namespace App\Enums;

enum TransactionDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function multiplier(): int
    {
        return $this === self::In ? 1 : -1;
    }
}
