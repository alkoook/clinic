<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
