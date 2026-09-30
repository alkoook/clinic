<?php

namespace Database\Factories;

use App\Enums\ParticipantPaymentStatus;
use App\Enums\PatientSource;
use App\Models\Patient;
use App\Models\TreatmentSession;
use App\Models\TreatmentSessionParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentSessionParticipant>
 */
class TreatmentSessionParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalPrice = fake()->numberBetween(20, 150) * 1000;
        $discountAmount = fake()->randomElement([0, 0, 0, 5000]);
        $finalAmount = $originalPrice - $discountAmount;
        $practitionerPercentage = fake()->randomElement([40, 50, 60, 70]);
        $practitionerShare = (int) round($finalAmount * $practitionerPercentage / 100);

        return [
            'treatment_session_id' => TreatmentSession::factory(),
            'appointment_participant_id' => null,
            'patient_id' => fn (array $attributes) => Patient::factory()
                ->state([
                    'current_practitioner_id' => TreatmentSession::query()
                        ->findOrFail($attributes['treatment_session_id'])
                        ->practitioner_id,
                ])
                ->create()
                ->getKey(),
            'source_practitioner_id' => fn (array $attributes) => Patient::query()->findOrFail($attributes['patient_id'])->referring_practitioner_id,
            'patient_name' => fn (array $attributes) => Patient::query()->findOrFail($attributes['patient_id'])->full_name,
            'patient_source' => fn (array $attributes) => Patient::query()->findOrFail($attributes['patient_id'])->source,
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'practitioner_percentage' => $practitionerPercentage,
            'practitioner_share' => $practitionerShare,
            'clinic_share' => $finalAmount - $practitionerShare,
            'payment_status' => ParticipantPaymentStatus::Unpaid,
            'discount_approved_by' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['payment_status' => ParticipantPaymentStatus::Paid]);
    }

    public function practitionerSourced(): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => Patient::factory()->practitionerSourced(),
            'patient_source' => PatientSource::Practitioner,
        ]);
    }
}
