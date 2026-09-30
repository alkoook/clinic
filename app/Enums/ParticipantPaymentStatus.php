<?php

namespace App\Enums;

enum ParticipantPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'غير مدفوع',
            self::Paid => 'مدفوع',
            self::PartiallyRefunded => 'مسترد جزئيًا',
            self::Refunded => 'مسترد بالكامل',
        };
    }
}
