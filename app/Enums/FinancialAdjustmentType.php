<?php

namespace App\Enums;

enum FinancialAdjustmentType: string
{
    case PractitionerRefund = 'practitioner_refund';
    case ManualCorrection = 'manual_correction';
    case CarryForward = 'carry_forward';

    public function label(): string
    {
        return match ($this) {
            self::PractitionerRefund => 'تعديل استرداد على مستحق المعالج',
            self::ManualCorrection => 'تصحيح مالي يدوي',
            self::CarryForward => 'ترحيل رصيد',
        };
    }
}
