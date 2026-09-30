<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PractitionerEarning;
use App\Models\TreatmentSessionParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PractitionerEarning>
 */
class PractitionerEarningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'treatment_session_participant_id' => fn (array $attributes) => Payment::query()
                ->findOrFail($attributes['payment_id'])
                ->treatment_session_participant_id,
            'practitioner_id' => fn (array $attributes) => TreatmentSessionParticipant::query()
                ->findOrFail($attributes['treatment_session_participant_id'])
                ->treatmentSession
                ->practitioner_id,
            'financial_week_id' => fn (array $attributes) => Payment::query()
                ->findOrFail($attributes['payment_id'])
                ->financial_week_id,
            'gross_amount' => fn (array $attributes) => Payment::query()->findOrFail($attributes['payment_id'])->amount,
            'practitioner_percentage' => fn (array $attributes) => TreatmentSessionParticipant::query()
                ->findOrFail($attributes['treatment_session_participant_id'])
                ->practitioner_percentage,
            'amount' => fn (array $attributes) => TreatmentSessionParticipant::query()
                ->findOrFail($attributes['treatment_session_participant_id'])
                ->practitioner_share,
            'status' => 'accrued',
            'earned_at' => fn (array $attributes) => Payment::query()->findOrFail($attributes['payment_id'])->paid_at,
        ];
    }
}
