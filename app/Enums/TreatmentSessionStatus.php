<?php

namespace App\Enums;

enum TreatmentSessionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Ready = 'ready';
    case Completed = 'completed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'بانتظار الدفع',
            self::Ready => 'جاهزة',
            self::Completed => 'مكتملة',
            self::Refunded => 'مستردة',
            self::Cancelled => 'ملغاة',
        };
    }
}
