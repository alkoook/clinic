<?php

namespace Database\Factories;

use App\Models\FinancialAgreement;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAgreement>
 */
class FinancialAgreementFactory extends Factory
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
            'practitioner_percentage' => fake()->randomElement([40, 50, 60, 70]),
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => null,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => fake()->dateTimeBetween('-2 years', '-1 year'),
            'effective_to' => fake()->dateTimeBetween('-11 months', '-1 day'),
            'is_active' => false,
        ]);
    }
}
