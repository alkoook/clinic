<?php

namespace Database\Factories;

use App\Enums\TreatmentSessionStatus;
use App\Models\Appointment;
use App\Models\TreatmentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentSession>
 */
class TreatmentSessionFactory extends Factory
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
            'practitioner_id' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->practitioner_id,
            'room_id' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->room_id,
            'treatment_type_id' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->treatment_type_id,
            'kind' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->kind,
            'status' => TreatmentSessionStatus::PendingPayment,
            'started_at' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->starts_at,
            'ended_at' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->ends_at,
            'completed_at' => null,
            'practitioner_name' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->practitioner->name,
            'room_name' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->room->name,
            'treatment_type_name' => fn (array $attributes) => Appointment::query()->findOrFail($attributes['appointment_id'])->treatmentType->name,
            'created_by' => User::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TreatmentSessionStatus::Ready]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreatmentSessionStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
