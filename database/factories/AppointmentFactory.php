<?php

namespace Database\Factories;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Practitioner;
use App\Models\Room;
use App\Models\TreatmentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::instance(fake()->dateTimeBetween('-1 week', '+1 month'))
            ->setMinute(fake()->randomElement([0, 15, 30, 45]))
            ->setSecond(0);
        $durationMinutes = fake()->randomElement([30, 45, 60, 90]);

        return [
            'practitioner_id' => Practitioner::factory(),
            'room_id' => Room::factory(),
            'treatment_type_id' => TreatmentType::factory(),
            'rescheduled_from_id' => null,
            'kind' => AppointmentKind::Individual,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify("+{$durationMinutes} minutes"),
            'duration_minutes' => $durationMinutes,
            'status' => AppointmentStatus::Scheduled,
            'notes' => fake()->optional()->sentence(),
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function group(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => AppointmentKind::Group,
            'treatment_type_id' => TreatmentType::factory()->group(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => fake()->sentence(),
            'cancelled_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
        ]);
    }
}
