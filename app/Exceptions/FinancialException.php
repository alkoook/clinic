<?php

namespace App\Exceptions;

use RuntimeException;

final class FinancialException extends RuntimeException
{
    public static function invalidAmount(string $message = 'يجب أن يكون المبلغ عددًا صحيحًا موجبًا بالليرة السورية.'): self
    {
        return new self($message);
    }

    public static function invalidIdempotencyKey(): self
    {
        return new self('تعذر التحقق من معرّف العملية. حدّث الصفحة وحاول مجددًا.');
    }

    public static function duplicateOperation(): self
    {
        return new self('استُخدم معرّف العملية سابقًا لعملية مختلفة. حدّث الصفحة وحاول مجددًا.');
    }

    public static function alreadyPaid(): self
    {
        return new self('تم تسجيل دفعة لهذا المشارك مسبقًا.');
    }

    public static function discountNotAuthorized(): self
    {
        return new self('لا تملك الصلاحية اللازمة لاعتماد هذا الخصم.');
    }

    public static function missingAgreement(): self
    {
        return new self('لا يوجد اتفاق مالي فعّال للمعالج في تاريخ الجلسة.');
    }

    public static function overlappingAgreement(): self
    {
        return new self('يوجد اتفاق مالي فعّال يتداخل مع فترة السريان المطلوبة.');
    }

    public static function invalidPercentage(): self
    {
        return new self('يجب أن تكون نسبة المعالج بين 0 و100 بالمئة.');
    }

    public static function closedWeek(): self
    {
        return new self('لا يمكن تسجيل عملية مالية ضمن أسبوع مغلق.');
    }

    public static function insufficientCash(): self
    {
        return new self('لا يحتوي الصندوق على رصيد نقدي كافٍ لتنفيذ العملية.');
    }

    public static function immutableLedgerEntry(): self
    {
        return new self('لا يمكن تعديل أو حذف حركة صندوق نهائية.');
    }

    public static function refundExceedsPayment(): self
    {
        return new self('مبلغ الاسترداد أكبر من الرصيد المتبقي القابل للاسترداد.');
    }

    public static function alreadyClosed(): self
    {
        return new self('تم إغلاق هذا الأسبوع المالي مسبقًا.');
    }

    public static function weekNotEnded(): self
    {
        return new self('لا يمكن إغلاق الأسبوع المالي قبل انتهاء يوم الخميس الخاص به.');
    }

    public static function olderWeekOpen(): self
    {
        return new self('يجب إغلاق أقدم أسبوع مالي مفتوح أولًا لضمان ترحيل الأرصدة بالترتيب الصحيح.');
    }

    public static function openWeekAlreadyExists(): self
    {
        return new self('توجد فترة مالية مفتوحة بالفعل. أغلقها قبل فتح فترة جديدة.');
    }

    public static function reopeningUnsafe(): self
    {
        return new self('لا يمكن إعادة فتح هذه الفترة لأن إغلاقها أنشأ حركة صندوق أو توجد تسويات مدفوعة تعتمد عليه.');
    }

    public static function settlementNotPayable(): self
    {
        return new self('لا تحتوي التسوية على رصيد قابل للدفع أو تم ترحيلها إلى تسوية لاحقة.');
    }

    public static function practitionerShareWaiverExceedsLiability(): self
    {
        return new self('مبلغ التنازل أكبر من مستحقات المعالج غير المدفوعة.');
    }

    public static function permissionDenied(string $permission): self
    {
        return new self('لا تملك الصلاحية اللازمة لتنفيذ هذه العملية المالية.');
    }

    public static function weekMismatch(): self
    {
        return new self('لا يتطابق الأسبوع المالي مع تاريخ العملية.');
    }
}
