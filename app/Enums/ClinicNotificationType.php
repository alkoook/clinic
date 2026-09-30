<?php

namespace App\Enums;

enum ClinicNotificationType: string
{
    case PatientCreated = 'patient_created';
    case AppointmentCreated = 'appointment_created';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case AppointmentCancelled = 'appointment_cancelled';
    case AppointmentPractitionerChanged = 'appointment_practitioner_changed';
    case AppointmentRoomChanged = 'appointment_room_changed';
    case SessionCompleted = 'session_completed';
    case PaymentReceived = 'payment_received';
    case PaymentRefunded = 'payment_refunded';
    case ExpenseCreated = 'expense_created';
    case PractitionerSettlementRecorded = 'practitioner_settlement_recorded';
    case PractitionerAdjustmentCreated = 'practitioner_adjustment_created';
    case CashboxTransferCreated = 'cashbox_transfer_created';
    case SideCashboxDeposit = 'side_cashbox_deposit';
    case SideCashboxWithdrawal = 'side_cashbox_withdrawal';
    case FinancialWeekClosed = 'financial_week_closed';
    case ReportExportReady = 'report_export_ready';
    case ReportExportFailed = 'report_export_failed';

    public function label(): string
    {
        return match ($this) {
            self::PatientCreated => 'مريض جديد',
            self::AppointmentCreated => 'موعد جديد',
            self::AppointmentRescheduled => 'إعادة جدولة موعد',
            self::AppointmentCancelled => 'إلغاء موعد',
            self::AppointmentPractitionerChanged => 'تغيير معالج الموعد',
            self::AppointmentRoomChanged => 'تغيير غرفة الموعد',
            self::SessionCompleted => 'اكتمال جلسة',
            self::PaymentReceived => 'دفعة',
            self::PaymentRefunded => 'استرداد',
            self::ExpenseCreated => 'مصروف',
            self::PractitionerSettlementRecorded => 'تسوية معالج',
            self::PractitionerAdjustmentCreated => 'تعديل استحقاق',
            self::CashboxTransferCreated => 'تحويل صندوق',
            self::SideCashboxDeposit => 'إيداع صندوق جانبي',
            self::SideCashboxWithdrawal => 'سحب صندوق جانبي',
            self::FinancialWeekClosed => 'إغلاق أسبوع مالي',
            self::ReportExportReady => 'تقرير جاهز',
            self::ReportExportFailed => 'تعذر التقرير',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AppointmentCreated,
            self::AppointmentRescheduled,
            self::AppointmentCancelled,
            self::AppointmentPractitionerChanged,
            self::AppointmentRoomChanged => 'appointments',
            self::SessionCompleted => 'sessions',
            self::PaymentReceived => 'payments',
            self::PaymentRefunded => 'refunds',
            self::ExpenseCreated => 'expenses',
            self::PractitionerSettlementRecorded,
            self::PractitionerAdjustmentCreated => 'settlements',
            self::CashboxTransferCreated,
            self::SideCashboxDeposit,
            self::SideCashboxWithdrawal => 'cashboxes',
            self::FinancialWeekClosed => 'weekly_closing',
            self::PatientCreated => 'patients',
            self::ReportExportReady,
            self::ReportExportFailed => 'reports',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PatientCreated => 'patients',
            self::AppointmentCreated,
            self::AppointmentRescheduled,
            self::AppointmentCancelled,
            self::AppointmentPractitionerChanged,
            self::AppointmentRoomChanged => 'calendar',
            self::SessionCompleted => 'check',
            self::PaymentReceived,
            self::PaymentRefunded => 'cashbox',
            self::ExpenseCreated => 'expenses',
            self::PractitionerSettlementRecorded => 'settlements',
            self::PractitionerAdjustmentCreated => 'audit',
            self::CashboxTransferCreated,
            self::SideCashboxDeposit,
            self::SideCashboxWithdrawal => 'cashbox',
            self::FinancialWeekClosed => 'reports',
            self::ReportExportReady,
            self::ReportExportFailed => 'reports',
        };
    }
}
