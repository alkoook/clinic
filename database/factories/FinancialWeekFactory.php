<?php

namespace Database\Factories;

use App\Enums\FinancialWeekStatus;
use App\Models\FinancialWeek;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FinancialWeek>
 */
class FinancialWeekFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = Carbon::now()
            ->startOfWeek(Carbon::SATURDAY)
            ->addWeeks(fake()->unique()->numberBetween(-260, 260))
            ->startOfDay();

        return [
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays(5),
            'status' => FinancialWeekStatus::Open,
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FinancialWeekStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
