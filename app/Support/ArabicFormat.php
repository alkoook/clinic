<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

final class ArabicFormat
{
    public static function appointmentDate(CarbonInterface|DateTimeInterface|string $date): string
    {
        return CarbonImmutable::parse($date)->locale('ar')->translatedFormat('l d/m/Y');
    }

    public static function dateTime(CarbonInterface|DateTimeInterface|string $date): string
    {
        return self::appointmentDate($date).' · '.CarbonImmutable::parse($date)->locale('ar')->translatedFormat('h:i a');
    }

    public static function money(int|float|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0)).' ل.س';
    }
}
