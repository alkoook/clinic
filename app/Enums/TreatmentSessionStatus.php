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

    public function badgeClass(): string
    {
        return match ($this) {
            self::PendingPayment => 'status-warning',
            self::Ready => 'status-success',
            self::Completed => 'status-completed',
            self::Refunded => 'status-refunded',
            self::Cancelled => 'status-neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PendingPayment => 'clock',
            self::Ready, self::Completed => 'check',
            self::Refunded => 'audit',
            self::Cancelled => 'close',
        };
    }
}
