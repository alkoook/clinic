<?php

namespace Database\Factories;

use App\Models\Specialty;
use App\Models\TreatmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentType>
 */
class TreatmentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'specialty_id' => Specialty::factory(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_group' => false,
            'is_active' => true,
        ];
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => ['is_group' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
