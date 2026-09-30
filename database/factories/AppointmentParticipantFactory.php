<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentParticipant;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentParticipant>
 */
class AppointmentParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'patient_id' => fn (array $attributes) => Patient::factory()
                ->state([
                    'current_practitioner_id' => Appointment::query()
                        ->findOrFail($attributes['appointment_id'])
                        ->practitioner_id,
                ])
                ->create()
                ->getKey(),
            'status' => AppointmentStatus::Scheduled,
        ];
    }

    public function attended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AppointmentStatus::Attended]);
    }
}
