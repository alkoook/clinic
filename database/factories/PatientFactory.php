<?php

namespace Database\Factories;

use App\Enums\PatientSource;
use App\Models\Patient;
use App\Models\Practitioner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'medical_file_number' => (string) fake()->unique()->numberBetween(1, 999999),
            'full_name' => fake()->firstName().' '.fake()->firstName().' '.fake()->lastName(),
            'age' => fake()->numberBetween(1, 100),
            'gender' => fake()->randomElement(['male', 'female']),
            'marital_status' => fake()->randomElement(['single', 'married', 'divorced', 'widowed']),
            'occupation' => fake()->jobTitle(),
            'companion_name' => fake()->optional()->name(),
            'phone' => fake()->optional()->phoneNumber(),
            'incoming_hospital_name' => null,
            'source' => PatientSource::Clinic,
            'referring_practitioner_id' => null,
            'current_practitioner_id' => Practitioner::factory(),
            'was_referred_out' => false,
            'outgoing_hospital_name' => null,
            'referred_out_at' => null,
            'referral_notes' => null,
            'administrative_notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function practitionerSourced(?Practitioner $practitioner = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => PatientSource::Practitioner,
            'referring_practitioner_id' => $practitioner?->getKey() ?? Practitioner::factory(),
        ]);
    }

    public function referredOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'was_referred_out' => true,
            'outgoing_hospital_name' => fake()->company().' Hospital',
            'referred_out_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'referral_notes' => fake()->sentence(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
