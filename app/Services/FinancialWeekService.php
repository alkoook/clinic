<?php

namespace App\Services;

use App\Enums\FinancialWeekStatus;
use App\Exceptions\FinancialException;
use App\Models\FinancialWeek;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

final class FinancialWeekService
{
    /**
     * Friday is intentionally assigned to the financial week beginning on the
     * following Saturday. Every other day resolves to the preceding Saturday.
     *
     * @return array{starts_on: CarbonImmutable, ends_on: CarbonImmutable}
     */
    public function boundariesFor(CarbonInterface|DateTimeInterface|string $date): array
    {
        $day = $this->date($date)->startOfDay();
        $start = match ($day->dayOfWeek) {
            CarbonInterface::FRIDAY => $day->addDay(),
            CarbonInterface::SATURDAY => $day,
            default => $day->subDays($day->dayOfWeek + 1),
        };

        return [
            'starts_on' => $start,
            'ends_on' => $start->addDays(5),
        ];
    }

    public function forDate(
        CarbonInterface|DateTimeInterface|string $date,
        bool $lockForUpdate = false,
    ): FinancialWeek {
        $moment = $this->date($date);
        $activeQuery = FinancialWeek::query()
            ->where('status', FinancialWeekStatus::Open->value)
            ->whereNotNull('opened_by')
            ->where('opened_at', '<=', $moment)
            ->orderByDesc('opened_at')
            ->orderByDesc('id');

        if ($lockForUpdate) {
            $activeQuery->lockForUpdate();
        }

        $active = $activeQuery->first();

        if ($active !== null) {
            return $active;
        }

        $boundaries = $this->boundariesFor($moment);
        $startsOn = $boundaries['starts_on']->toDateString();
        $query = FinancialWeek::query()->whereDate('starts_on', $startsOn);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var FinancialWeek|null $week */
        $week = $query->first();

        if ($week === null) {
            $week = FinancialWeek::query()->create([
                'starts_on' => $startsOn,
                'ends_on' => $boundaries['ends_on']->toDateString(),
                'opened_at' => $boundaries['starts_on'],
                'status' => FinancialWeekStatus::Open->value,
            ]);
        }

        return $week;
    }

    public function current(?CarbonInterface $at = null, bool $lockForUpdate = false): FinancialWeek
    {
        return $this->forDate($at ?? now(), $lockForUpdate);
    }

    public function openNew(User $actor, ?CarbonInterface $at = null): FinancialWeek
    {
        if (! $actor->hasPermission('cashboxes.manage')) {
            throw FinancialException::permissionDenied('cashboxes.manage');
        }

        return DB::transaction(function () use ($actor, $at): FinancialWeek {
            if (FinancialWeek::query()->where('status', FinancialWeekStatus::Open->value)->lockForUpdate()->exists()) {
                throw FinancialException::openWeekAlreadyExists();
            }

            $openedAt = $this->date($at ?? now());

            return FinancialWeek::query()->create([
                'starts_on' => $openedAt->toDateString(),
                'ends_on' => $openedAt->addDays(6)->toDateString(),
                'opened_at' => $openedAt,
                'opened_by' => $actor->getKey(),
                'status' => FinancialWeekStatus::Open->value,
            ]);
        }, 3);
    }

    public function assertOpen(FinancialWeek $week): void
    {
        $status = $week->getAttribute('status');
        $value = $status instanceof FinancialWeekStatus ? $status->value : (string) $status;

        if ($value !== FinancialWeekStatus::Open->value) {
            throw FinancialException::closedWeek();
        }
    }

    public function contains(
        FinancialWeek $week,
        CarbonInterface|DateTimeInterface|string $date,
    ): bool {
        if ($week->getAttribute('opened_by') !== null
            && $week->getAttribute('status') === FinancialWeekStatus::Open
            && $this->date($date)->greaterThanOrEqualTo($this->date($week->getAttribute('opened_at')))) {
            return true;
        }

        $boundaries = $this->boundariesFor($date);

        return $this->dateValue($week->getAttribute('starts_on')) === $boundaries['starts_on']->toDateString()
            && $this->dateValue($week->getAttribute('ends_on')) === $boundaries['ends_on']->toDateString();
    }

    private function date(CarbonInterface|DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->setTimezone((string) config('app.timezone'));
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone((string) config('app.timezone'));
        }

        return CarbonImmutable::parse($value, (string) config('app.timezone'));
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        return CarbonImmutable::parse((string) $value)->toDateString();
    }
}
