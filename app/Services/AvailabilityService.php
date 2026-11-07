<?php

namespace App\Services;

use App\Enums\AvailabilityExceptionType;
use App\Exceptions\SchedulingException;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use App\Models\PractitionerAvailabilityException;
use App\Models\TreatmentSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

final class AvailabilityService
{
    /** @return list<array{start: CarbonImmutable, end: CarbonImmutable}> */
    public function bookingIntervals(int $practitionerId, string $date, int $roomId, ?int $excludeAppointmentId = null): array
    {
        $periods = $this->intervalsFor($practitionerId, $date);
        $day = CarbonImmutable::parse($date);
        $bookings = Appointment::query()
            ->where(fn ($query) => $query->where('practitioner_id', $practitionerId)->orWhere('room_id', $roomId))
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->when($excludeAppointmentId, fn ($query) => $query->whereKeyNot($excludeAppointmentId))
            ->where('starts_at', '<', $day->addDay())->where('ends_at', '>', $day)
            ->get(['starts_at', 'ends_at']);
        foreach ($bookings as $booking) {
            $periods = $this->subtract($periods, ['start' => CarbonImmutable::instance($booking->starts_at), 'end' => CarbonImmutable::instance($booking->ends_at)]);
        }
        $sessions = TreatmentSession::query()
            ->where(fn ($query) => $query->where('practitioner_id', $practitionerId)->orWhere('room_id', $roomId))
            ->whereNot('status', 'cancelled')->where('started_at', '<', $day->addDay())
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $day))
            ->get(['started_at', 'ended_at']);
        foreach ($sessions as $session) {
            $periods = $this->subtract($periods, ['start' => CarbonImmutable::instance($session->started_at), 'end' => $session->ended_at ? CarbonImmutable::instance($session->ended_at) : $day->addDay()]);
        }

        return $periods;
    }

    /**
     * Return the effective availability periods for a local calendar date.
     * Periods are normalized and represented as half-open [start, end) ranges.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function intervalsFor(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $date,
    ): array {
        $practitionerId = $practitioner instanceof Practitioner
            ? (int) $practitioner->getKey()
            : $practitioner;
        $localDate = $this->date($date)->startOfDay();
        $dateString = $localDate->toDateString();

        $periods = [];
        $recurringPeriods = PractitionerAvailability::query()
            ->where('practitioner_id', $practitionerId)
            ->where('day_of_week', $localDate->dayOfWeek)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($dateString): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $dateString);
            })
            ->where(function (Builder $query) use ($dateString): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $dateString);
            })
            ->get();

        foreach ($recurringPeriods as $recurringPeriod) {
            $periods[] = $this->period(
                $localDate,
                $recurringPeriod->getAttribute('start_time'),
                $recurringPeriod->getAttribute('end_time'),
            );
        }

        $exceptions = PractitionerAvailabilityException::query()
            ->where('practitioner_id', $practitionerId)
            ->whereDate('date', $dateString)
            ->orderBy('id')
            ->get();

        foreach ($exceptions as $exception) {
            if ($this->exceptionType($exception) !== AvailabilityExceptionType::Available) {
                continue;
            }

            $periods[] = $this->period(
                $localDate,
                $exception->getAttribute('start_time'),
                $exception->getAttribute('end_time'),
            );
        }

        $periods = $this->normalize($periods);

        foreach ($exceptions as $exception) {
            if ($this->exceptionType($exception) !== AvailabilityExceptionType::Unavailable) {
                continue;
            }

            $startTime = $exception->getAttribute('start_time');
            $endTime = $exception->getAttribute('end_time');
            $unavailable = $startTime === null || $endTime === null
                ? ['start' => $localDate, 'end' => $localDate->addDay()]
                : $this->period($localDate, $startTime, $endTime);
            $periods = $this->subtract($periods, $unavailable);
        }

        return $this->normalize($periods);
    }

    public function isAvailable(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $startsAt,
        CarbonInterface|DateTimeInterface|string $endsAt,
    ): bool {
        $start = $this->date($startsAt);
        $end = $this->date($endsAt);

        if ($end->lessThanOrEqualTo($start) || ! $start->isSameDay($end)) {
            return false;
        }

        foreach ($this->intervalsFor($practitioner, $start) as $period) {
            if ($start->greaterThanOrEqualTo($period['start']) && $end->lessThanOrEqualTo($period['end'])) {
                return true;
            }
        }

        return false;
    }

    public function assertAvailable(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $startsAt,
        CarbonInterface|DateTimeInterface|string $endsAt,
    ): void {
        if (! $this->isAvailable($practitioner, $startsAt, $endsAt)) {
            throw SchedulingException::practitionerUnavailable();
        }
    }

    public function isAvailableAt(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $at,
    ): bool {
        $time = $this->date($at);

        foreach ($this->intervalsFor($practitioner, $time) as $period) {
            if ($time->greaterThanOrEqualTo($period['start']) && $time->lessThan($period['end'])) {
                return true;
            }
        }

        return false;
    }

    public function assertAvailableAt(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $at,
    ): void {
        if (! $this->isAvailableAt($practitioner, $at)) {
            throw SchedulingException::practitionerUnavailable();
        }
    }

    private function exceptionType(PractitionerAvailabilityException $exception): AvailabilityExceptionType
    {
        $type = $exception->getAttribute('type');

        return $type instanceof AvailabilityExceptionType
            ? $type
            : AvailabilityExceptionType::from((string) $type);
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function period(CarbonImmutable $date, mixed $startTime, mixed $endTime): array
    {
        if ($startTime === null || $endTime === null) {
            throw SchedulingException::invalidAvailabilityPeriod();
        }

        $start = $this->atTime($date, $startTime);
        $end = $this->atTime($date, $endTime);

        if ($end->lessThanOrEqualTo($start)) {
            throw SchedulingException::invalidAvailabilityPeriod();
        }

        return ['start' => $start, 'end' => $end];
    }

    private function atTime(CarbonImmutable $date, mixed $time): CarbonImmutable
    {
        $timeValue = $time instanceof DateTimeInterface
            ? $time->format('H:i:s')
            : (string) $time;

        return CarbonImmutable::parse(
            $date->toDateString().' '.$timeValue,
            (string) config('app.timezone'),
        );
    }

    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $periods
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function normalize(array $periods): array
    {
        usort(
            $periods,
            static fn (array $left, array $right): int => $left['start']->getTimestamp() <=> $right['start']->getTimestamp(),
        );

        $normalized = [];

        foreach ($periods as $period) {
            $lastIndex = array_key_last($normalized);

            if ($lastIndex === null || $period['start']->greaterThan($normalized[$lastIndex]['end'])) {
                $normalized[] = $period;

                continue;
            }

            if ($period['end']->greaterThan($normalized[$lastIndex]['end'])) {
                $normalized[$lastIndex]['end'] = $period['end'];
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $periods
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $unavailable
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function subtract(array $periods, array $unavailable): array
    {
        $remaining = [];

        foreach ($periods as $period) {
            $doesNotOverlap = $unavailable['end']->lessThanOrEqualTo($period['start'])
                || $unavailable['start']->greaterThanOrEqualTo($period['end']);

            if ($doesNotOverlap) {
                $remaining[] = $period;

                continue;
            }

            if ($unavailable['start']->greaterThan($period['start'])) {
                $remaining[] = [
                    'start' => $period['start'],
                    'end' => $unavailable['start']->min($period['end']),
                ];
            }

            if ($unavailable['end']->lessThan($period['end'])) {
                $remaining[] = [
                    'start' => $unavailable['end']->max($period['start']),
                    'end' => $period['end'],
                ];
            }
        }

        return $remaining;
    }

    private function date(CarbonInterface|DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone((string) config('app.timezone'));
        }

        return CarbonImmutable::parse($value, (string) config('app.timezone'));
    }
}
