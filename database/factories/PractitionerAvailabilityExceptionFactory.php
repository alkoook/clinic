<?php

namespace Database\Factories;

use App\Enums\AvailabilityExceptionType;
use App\Models\Practitioner;
use App\Models\PractitionerAvailabilityException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PractitionerAvailabilityException>
 */
class PractitionerAvailabilityExceptionFactory extends Factory
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
            'date' => fake()->dateTimeBetween('now', '+3 months'),
            'type' => AvailabilityExceptionType::Unavailable,
            'start_time' => null,
            'end_time' => null,
            'reason' => fake()->optional()->sentence(),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AvailabilityExceptionType::Available,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
        ]);
    }
}
