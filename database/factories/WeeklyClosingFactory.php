<?php

namespace Database\Factories;

use App\Models\FinancialWeek;
use App\Models\User;
use App\Models\WeeklyClosing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyClosing>
 */
class WeeklyClosingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $collections = fake()->numberBetween(500, 2000) * 1000;
        $practitionerShares = (int) round($collections * 0.6);
        $expenses = fake()->numberBetween(20, 100) * 1000;
        $refunds = 0;
        $adjustments = 0;
        $calculatedNet = $collections - $practitionerShares - $expenses - $refunds + $adjustments;

        return [
            'financial_week_id' => FinancialWeek::factory()->closed(),
            'cashbox_transfer_id' => null,
            'paid_sessions_count' => fake()->numberBetween(5, 30),
            'collections' => $collections,
            'practitioner_shares' => $practitionerShares,
            'expenses' => $expenses,
            'refunds' => $refunds,
            'adjustments' => $adjustments,
            'calculated_net' => $calculatedNet,
            'available_cash' => max(0, $calculatedNet),
            'reserved_practitioner_liabilities' => $practitionerShares,
            'transferred_surplus' => max(0, $calculatedNet),
            'deficit_covered' => max(0, -$calculatedNet),
            'practitioner_breakdown' => [],
            'closed_at' => fn (array $attributes) => FinancialWeek::query()
                ->findOrFail($attributes['financial_week_id'])
                ->ends_on
                ->endOfDay(),
            'closed_by' => User::factory(),
        ];
    }
}
