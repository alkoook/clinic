<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['appointment_id', 'patient_id', 'status'])]
class AppointmentParticipant extends Model
{
    /** @use HasFactory<AppointmentParticipantFactory> */
    use HasFactory;

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function treatmentSessionParticipant(): HasOne
    {
        return $this->hasOne(TreatmentSessionParticipant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => AppointmentStatus::class];
    }
}
