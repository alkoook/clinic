<?php

namespace App\Services;

use App\Exceptions\SchedulingException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class ClinicWorkingHoursService
{
    /** @return array<int, array{enabled: bool, start: string, end: string}> */
    public function schedule(): array
    {
        $stored = app(SettingsService::class)->string('clinic.working_hours');
        $decoded = is_string($stored) ? json_decode($stored, true) : null;

        return collect(range(0, 6))->mapWithKeys(function (int $day) use ($decoded): array {
            $period = is_array($decoded) && is_array($decoded[$day] ?? null) ? $decoded[$day] : [];

            return [$day => [
                'enabled' => (bool) ($period['enabled'] ?? true),
                'start' => (string) ($period['start'] ?? '00:00'),
                'end' => (string) ($period['end'] ?? '23:59'),
            ]];
        })->all();
    }

    public function assertWithinWorkingHours(CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $start = CarbonImmutable::instance($startsAt);
        $end = CarbonImmutable::instance($endsAt);
        $period = $this->schedule()[$start->dayOfWeek];

        if (! $period['enabled']) {
            throw SchedulingException::clinicClosed();
        }

        $opening = $start->setTimeFromTimeString($period['start']);
        $closing = $start->setTimeFromTimeString($period['end']);

        if ($start->lt($opening) || $end->gt($closing)) {
            throw SchedulingException::outsideClinicWorkingHours();
        }
    }
}
