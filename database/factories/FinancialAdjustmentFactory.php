<?php

namespace Database\Factories;

use App\Enums\FinancialAdjustmentType;
use App\Models\FinancialAdjustment;
use App\Models\FinancialWeek;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAdjustment>
 */
class FinancialAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practitioner_id' => Practitioner::factory(),
            'financial_week_id' => FinancialWeek::factory(),
            'refund_id' => null,
            'type' => FinancialAdjustmentType::ManualCorrection,
            'amount' => fake()->randomElement([-1, 1]) * fake()->numberBetween(1, 50) * 1000,
            'reason' => fake()->sentence(),
            'occurred_at' => fn (array $attributes) => FinancialWeek::query()
                ->findOrFail($attributes['financial_week_id'])
                ->starts_on
                ->addDay()
                ->setTime(12, 0),
            'created_by' => User::factory(),
        ];
    }
}
