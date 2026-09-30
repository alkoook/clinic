<?php

namespace App\Support;

use App\Enums\FinancialAdjustmentType;
use App\Enums\PatientSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\CashboxTransaction;
use App\Models\CashboxTransfer;
use App\Models\Expense;
use App\Models\FinancialAdjustment;
use App\Models\FinancialAgreement;
use App\Models\FinancialAuditLog;
use App\Models\Payment;
use App\Models\PractitionerSettlement;
use App\Models\PractitionerSettlementPayment;
use App\Models\Refund;
use App\Models\WeeklyClosing;
use BackedEnum;

final class PresentationLabels
{
    /** @return array<class-string, string> */
    public static function auditEntities(): array
    {
        return [
            Payment::class => 'الدفعات',
            Refund::class => 'الاستردادات',
            Expense::class => 'المصروفات',
            FinancialAgreement::class => 'الاتفاقات المالية',
            FinancialAdjustment::class => 'التعديلات المالية',
            CashboxTransaction::class => 'حركات الصناديق',
            CashboxTransfer::class => 'التحويلات بين الصناديق',
            WeeklyClosing::class => 'الإغلاقات الأسبوعية',
            PractitionerSettlement::class => 'تسويات المعالجين',
            PractitionerSettlementPayment::class => 'دفعات التسويات',
        ];
    }

    public static function auditTone(string $action): string
    {
        return match (true) {
            str_starts_with($action, 'payment.') || str_contains($action, 'deposit') => 'success',
            str_starts_with($action, 'refund.'), str_starts_with($action, 'expense.'), str_contains($action, 'withdrawal') => 'danger',
            str_contains($action, 'closing'), str_contains($action, 'settlement') => 'warning',
            default => 'info',
        };
    }

    /** @return array<string, string> */
    public static function auditActions(): array
    {
        return [
            'payment.created' => 'تسجيل دفعة', 'refund.created' => 'تسجيل استرداد',
            'expense.created' => 'تسجيل مصروف', 'cashbox.deposit' => 'إيداع في صندوق',
            'cashbox.withdrawal' => 'سحب من صندوق', 'cashbox_transfer.created' => 'تحويل بين الصناديق',
            'weekly_closing.created' => 'إغلاق أسبوع مالي', 'practitioner_settlement.created' => 'إنشاء تسوية معالج',
            'practitioner_settlement.payment_created' => 'دفع مستحق معالج',
            'financial_adjustment.created' => 'إنشاء تعديل مالي',
            'financial_agreement.created' => 'إنشاء اتفاق مالي', 'financial_agreement.updated' => 'تعديل اتفاق مالي',
            'financial_agreement.archived' => 'أرشفة اتفاق مالي', 'financial_week.reopened' => 'إعادة فتح فترة مالية',
        ];
    }

    public static function auditAction(string $action): string
    {
        return self::auditActions()[$action] ?? 'عملية مالية موثقة';
    }

    public static function entity(?string $type): string
    {
        return match (class_basename((string) $type)) {
            'Payment' => 'دفعة جلسة علاجية', 'Refund' => 'استرداد دفعة', 'Expense' => 'مصروف عيادة',
            'FinancialAgreement' => 'اتفاق مالي', 'FinancialAdjustment' => 'تعديل مالي',
            'CashboxTransaction' => 'حركة صندوق', 'CashboxTransfer' => 'تحويل بين صندوقين',
            'WeeklyClosing' => 'إغلاق أسبوع مالي', 'PractitionerSettlement' => 'تسوية مستحقات معالج',
            'PractitionerSettlementPayment' => 'دفعة مستحقات معالج', default => 'سجل مالي',
        };
    }

    public static function entityDescription(FinancialAuditLog $log): string
    {
        return self::entity($log->auditable_type);
    }

    public static function field(string $field): ?string
    {
        if (in_array($field, ['id', 'idempotency_key', 'created_by', 'updated_by', 'created_at', 'updated_at', 'cashbox_transaction_id', 'out_transaction_id', 'in_transaction_id'], true)) {
            return null;
        }

        return [
            'amount' => 'المبلغ', 'original_price' => 'السعر الأصلي', 'discount_amount' => 'الخصم',
            'final_amount' => 'المبلغ النهائي', 'gross_amount' => 'المبلغ الإجمالي',
            'practitioner_percentage' => 'نسبة المعالج', 'practitioner_share' => 'حصة المعالج',
            'clinic_share' => 'حصة العيادة', 'status' => 'الحالة', 'method' => 'طريقة الدفع',
            'patient_source' => 'مصدر المريض', 'reason' => 'السبب', 'description' => 'البيان',
            'occurred_at' => 'وقت العملية', 'paid_at' => 'وقت الدفع', 'refunded_at' => 'وقت الاسترداد',
            'effective_from' => 'ساري من', 'effective_to' => 'ساري إلى', 'is_active' => 'نشط',
            'affects_weekly_net' => 'يؤثر في صافي الأسبوع', 'opening_balance' => 'الرصيد الافتتاحي',
            'earnings_amount' => 'المستحقات المكتسبة', 'adjustments_amount' => 'التعديلات',
            'total_due' => 'إجمالي المستحق', 'paid_amount' => 'المدفوع', 'remaining_amount' => 'المتبقي',
            'collections' => 'التحصيلات', 'refunds' => 'الاستردادات', 'expenses' => 'المصروفات',
            'calculated_net' => 'الصافي المحسوب', 'transferred_surplus' => 'الفائض المحول',
            'deficit_covered' => 'العجز المغطى', 'direction' => 'الاتجاه', 'type' => 'نوع العملية',
        ][$field] ?? null;
    }

    public static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'غير محدد';
        }
        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $labels = collect([
            ...self::enumLabels(TransactionType::cases()), ...self::enumLabels(TransactionDirection::cases()),
            ...self::enumLabels(PaymentMethod::cases()), ...self::enumLabels(PaymentStatus::cases()),
            ...self::enumLabels(RefundStatus::cases()), ...self::enumLabels(SettlementStatus::cases()),
            ...self::enumLabels(PatientSource::cases()), ...self::enumLabels(FinancialAdjustmentType::cases()),
            'true' => 'نعم', 'false' => 'لا', '1' => 'نعم', '0' => 'لا',
        ]);
        $text = (string) $value;
        if ($labels->has($text)) {
            return $labels[$text];
        }
        if (in_array($field, ['amount', 'original_price', 'discount_amount', 'final_amount', 'gross_amount', 'practitioner_share', 'clinic_share', 'opening_balance', 'earnings_amount', 'adjustments_amount', 'total_due', 'paid_amount', 'remaining_amount', 'collections', 'refunds', 'expenses', 'calculated_net', 'transferred_surplus', 'deficit_covered'], true) && is_numeric($value)) {
            return number_format((float) $value).' ل.س';
        }
        if ($field === 'practitioner_percentage' && is_numeric($value)) {
            return $value.'%';
        }

        return match ($text) {
            'Treatment session payment' => 'دفعة جلسة علاجية',
            'Practitioner settlement payment' => 'دفعة مستحقات معالج',
            'Weekly transferable surplus' => 'تحويل فائض الإغلاق الأسبوعي',
            'Weekly deficit coverage' => 'تغطية عجز الإغلاق الأسبوعي',
            default => $text,
        };
    }

    /** @return array<string, string> */
    private static function enumLabels(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn (BackedEnum $case): array => [
            (string) $case->value => method_exists($case, 'label') ? $case->label() : (string) $case->value,
        ])->all();
    }
}
