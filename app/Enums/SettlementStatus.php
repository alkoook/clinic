<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'غير مدفوع',
            self::PartiallyPaid => 'مدفوع جزئيًا',
            self::Paid => 'مدفوع',
        };
    }
}
