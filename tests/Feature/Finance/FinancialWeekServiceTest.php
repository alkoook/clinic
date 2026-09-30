<?php

use App\Models\FinancialWeek;
use App\Services\FinancialWeekService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('maps every operational day to its Saturday through Thursday financial week', function (string $date, string $startsOn, string $endsOn) {
    $boundaries = app(FinancialWeekService::class)->boundariesFor($date);

    expect($boundaries['starts_on']->toDateString())->toBe($startsOn)
        ->and($boundaries['ends_on']->toDateString())->toBe($endsOn);
})->with([
    'Saturday starts the week' => ['2026-09-05 09:00:00', '2026-09-05', '2026-09-10'],
    'Sunday stays in the same week' => ['2026-09-06 09:00:00', '2026-09-05', '2026-09-10'],
    'Monday stays in the same week' => ['2026-09-07 09:00:00', '2026-09-05', '2026-09-10'],
    'Tuesday stays in the same week' => ['2026-09-08 09:00:00', '2026-09-05', '2026-09-10'],
    'Wednesday stays in the same week' => ['2026-09-09 09:00:00', '2026-09-05', '2026-09-10'],
    'Thursday ends the week' => ['2026-09-10 09:00:00', '2026-09-05', '2026-09-10'],
]);

it('assigns Friday to the financial week beginning on the following Saturday', function () {
    $boundaries = app(FinancialWeekService::class)->boundariesFor('2026-09-11 23:59:59');

    expect($boundaries['starts_on']->toDateString())->toBe('2026-09-12')
        ->and($boundaries['ends_on']->toDateString())->toBe('2026-09-17');
});

it('returns one persisted week for all dates mapped to the same boundaries', function () {
    $service = app(FinancialWeekService::class);

    $saturdayWeek = $service->forDate('2026-09-05 12:00:00');
    $thursdayWeek = $service->forDate('2026-09-10 23:59:59');

    expect($thursdayWeek->is($saturdayWeek))->toBeTrue()
        ->and($service->contains($saturdayWeek, '2026-09-08 08:00:00'))->toBeTrue()
        ->and($service->contains($saturdayWeek, '2026-09-11 08:00:00'))->toBeFalse();
    $this->assertDatabaseCount((new FinancialWeek)->getTable(), 1);
});
