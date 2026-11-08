<?php

namespace App\Exceptions;

use RuntimeException;

final class SchedulingException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        string $message,
        public readonly string $reason = 'scheduling_error',
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function invalidInterval(): self
    {
        return new self('يجب أن تكون مدة الموعد بين 5 و720 دقيقة وأن يقع الموعد ضمن يوم واحد.', 'invalid_interval');
    }

    public static function invalidParticipants(): self
    {
        return new self('يتطلب الموعد الفردي مريضًا واحدًا، ويتطلب الموعد الجماعي مشاركًا واحدًا على الأقل.');
    }

    public static function practitionerUnavailable(): self
    {
        return new self('المعالج غير متاح لكامل مدة الموعد المحدد. اختر وقتًا آخر ضمن أوقات دوامه.', 'practitioner_unavailable');
    }

    public static function clinicClosed(): self
    {
        return new self('العيادة مغلقة في اليوم المحدد. اختر يومًا ضمن دوام العيادة.', 'clinic_closed');
    }

    public static function outsideClinicWorkingHours(): self
    {
        return new self('الموعد المحدد خارج ساعات دوام العيادة. اختر وقتًا يقع بالكامل ضمن الدوام.', 'outside_clinic_hours');
    }

    /** @param array<string, mixed> $details */
    public static function practitionerConflict(array $details = []): self
    {
        return new self('المعالج لديه موعد آخر يتداخل مع هذا الوقت. اختر وقتًا مختلفًا.', 'practitioner_conflict', $details);
    }

    /** @param array<string, mixed> $details */
    public static function roomConflict(array $details = []): self
    {
        return new self('الغرفة محجوزة خلال هذا الوقت. اختر غرفة أو موعدًا آخر.', 'room_conflict', $details);
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

    /** @param list<array{name: string, amount: int}> $participants */
    public static function unpaidParticipants(array $participants): self
    {
        $details = collect($participants)
            ->map(static fn (array $participant): string => $participant['name'].' — '.number_format($participant['amount']).' ل.س')
            ->join('، ');

        return new self('لا يمكن إكمال الجلسة لوجود رصيد غير مسدد: '.$details.'.');
    }

    public static function treatmentStillRunning(): self
    {
        return new self('يجب تسجيل نهاية العلاج الفعلية قبل اعتماد اكتمال الجلسة.', 'treatment_still_running');
    }

    public static function invalidTreatmentEnd(): self
    {
        return new self('يجب أن يكون وقت نهاية العلاج الفعلية بعد بدء الجلسة وألا يكون في المستقبل.', 'invalid_treatment_end');
    }

    public static function invalidSessionCompletion(): self
    {
        return new self('يجب ألا يسبق وقت اعتماد اكتمال الجلسة نهاية العلاج الفعلية وألا يكون في المستقبل.', 'invalid_session_completion');
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
