<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Voided = 'voided';
}
