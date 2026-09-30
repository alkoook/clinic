<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Attended = 'attended';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'مجدول',
            self::Attended => 'حضر',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغى',
            self::NoShow => 'لم يحضر',
            self::Rescheduled => 'أعيدت جدولته',
        };
    }

    public function blocksTime(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Rescheduled], true);
    }
}
