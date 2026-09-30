<?php

namespace App\Exceptions;

use RuntimeException;

final class SchedulingException extends RuntimeException
{
    public static function invalidInterval(): self
    {
        return new self('يجب أن تكون مدة الموعد دقائق صحيحة موجبة وأن يقع الموعد ضمن يوم واحد.');
    }

    public static function invalidParticipants(): self
    {
        return new self('يتطلب الموعد الفردي مريضًا واحدًا، ويتطلب الموعد الجماعي مشاركًا واحدًا على الأقل.');
    }

    public static function practitionerUnavailable(): self
    {
        return new self('المعالج غير متاح لكامل مدة الموعد المحدد. اختر وقتًا آخر ضمن أوقات دوامه.');
    }

    public static function clinicClosed(): self
    {
        return new self('العيادة مغلقة في اليوم المحدد. اختر يومًا ضمن دوام العيادة.');
    }

    public static function outsideClinicWorkingHours(): self
    {
        return new self('الموعد المحدد خارج ساعات دوام العيادة. اختر وقتًا يقع بالكامل ضمن الدوام.');
    }

    public static function practitionerConflict(): self
    {
        return new self('المعالج لديه موعد آخر يتداخل مع هذا الوقت. اختر وقتًا مختلفًا.');
    }

    public static function roomConflict(): self
    {
        return new self('الغرفة محجوزة خلال هذا الوقت. اختر غرفة أو موعدًا آخر.');
    }

    public static function inactiveResource(string $resource): self
    {
        return new self('العنصر المحدد غير نشط ولا يمكن استخدامه في الموعد.');
    }

    public static function invalidState(string $operation): self
    {
        return new self(match ($operation) {
            'completed before every participant pays' => 'لا يمكن إكمال الجلسة قبل تسجيل دفعات جميع المشاركين.',
            'rescheduled' => 'لا تسمح حالة الموعد الحالية بإعادة جدولته.',
            'cancelled' => 'لا تسمح حالة الموعد الحالية بإلغائه.',
            default => 'لا تسمح الحالة الحالية بتنفيذ هذه العملية.',
        });
    }

    public static function appointmentNotStarted(): self
    {
        return new self('لا يمكن إنشاء جلسة علاجية قبل حلول وقت الموعد.');
    }

    public static function appointmentTooEarly(int $minutes): self
    {
        return new self("يمكن بدء الجلسة قبل الموعد بـ {$minutes} دقيقة كحد أقصى.");
    }

    public static function missingParticipantPrice(int $appointmentParticipantId): self
    {
        return new self('يجب إدخال سعر صحيح للمشارك المحدد في الجلسة.');
    }

    public static function invalidAvailabilityPeriod(): self
    {
        return new self('يجب أن يبدأ وقت الدوام قبل وقت انتهائه.');
    }

    public static function treatmentKindMismatch(): self
    {
        return new self('يجب أن يتوافق نوع الموعد مع نوع العلاج المحدد.');
    }

    public static function permissionDenied(string $permission): self
    {
        return new self('لا تملك الصلاحية اللازمة لتنفيذ هذه العملية.');
    }
}
