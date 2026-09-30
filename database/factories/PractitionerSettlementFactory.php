<?php

namespace Database\Factories;

use App\Enums\SettlementStatus;
use App\Models\FinancialWeek;
use App\Models\Practitioner;
use App\Models\PractitionerSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PractitionerSettlement>
 */
class PractitionerSettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openingBalance = fake()->numberBetween(-50, 50) * 1000;
        $earningsAmount = fake()->numberBetween(100, 500) * 1000;
        $adjustmentsAmount = 0;
        $totalDue = $openingBalance + $earningsAmount + $adjustmentsAmount;

        return [
            'practitioner_id' => Practitioner::factory(),
            'financial_week_id' => FinancialWeek::factory(),
            'opening_balance' => $openingBalance,
            'earnings_amount' => $earningsAmount,
            'adjustments_amount' => $adjustmentsAmount,
            'total_due' => $totalDue,
            'paid_amount' => 0,
            'remaining_amount' => $totalDue,
            'status' => SettlementStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_amount' => max(0, $attributes['total_due']),
            'remaining_amount' => 0,
            'status' => SettlementStatus::Paid,
        ]);
    }
}
