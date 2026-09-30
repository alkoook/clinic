<?php

namespace App\Enums;

enum TransactionType: string
{
    case SessionPayment = 'session_payment';
    case Expense = 'expense';
    case Refund = 'refund';
    case PractitionerSettlement = 'practitioner_settlement';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case ManualDeposit = 'manual_deposit';
    case ManualWithdrawal = 'manual_withdrawal';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::SessionPayment => 'قبض جلسة',
            self::Expense => 'مصروف',
            self::Refund => 'استرداد',
            self::PractitionerSettlement => 'دفع مستحق طبيب / معالج',
            self::TransferOut => 'تحويل صادر',
            self::TransferIn => 'تحويل وارد',
            self::ManualDeposit => 'إيداع يدوي',
            self::ManualWithdrawal => 'سحب يدوي',
            self::Adjustment => 'تسوية مالية',
        };
    }
}
