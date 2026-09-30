<?php

namespace Database\Factories;

use App\Models\Practitioner;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Practitioner>
 */
class PractitionerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $practitionerRole = Role::query()->where('name', 'practitioner')->first()
            ?? Role::factory()->state([
                'name' => 'practitioner',
                'label' => 'Practitioner',
            ]);

        return [
            'user_id' => User::factory()->state(['role_id' => $practitionerRole]),
            'name' => fake()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'contact_data' => [
                'email' => fake()->safeEmail(),
            ],
            'administrative_notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function withSpecialties(int $count = 1): static
    {
        return $this->hasAttached(Specialty::factory()->count($count));
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
