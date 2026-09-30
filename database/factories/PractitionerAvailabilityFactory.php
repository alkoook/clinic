<?php

namespace Database\Factories;

use App\Models\Practitioner;
use App\Models\PractitionerAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PractitionerAvailability>
 */
class PractitionerAvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startHour = fake()->numberBetween(8, 15);

        return [
            'practitioner_id' => Practitioner::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', $startHour + fake()->numberBetween(2, 6)),
            'effective_from' => fake()->optional()->dateTimeBetween('-3 months', 'now'),
            'effective_to' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
