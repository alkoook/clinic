<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAttachment>
 */
class PatientAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'treatment_session_id' => null,
            'disk' => 'local',
            'path' => 'patient-attachments/'.fake()->uuid().'.pdf',
            'stored_name' => fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => fake()->numberBetween(10_000, 5_000_000),
            'title' => fake()->optional()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
