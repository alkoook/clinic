<?php

namespace App\Models;

use App\Enums\AppointmentKind;
use App\Enums\TreatmentSessionStatus;
use Database\Factories\TreatmentSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'appointment_id',
    'practitioner_id',
    'room_id',
    'treatment_type_id',
    'kind',
    'status',
    'started_at',
    'ended_at',
    'completed_at',
    'practitioner_name',
    'room_name',
    'treatment_type_name',
    'created_by',
])]
class TreatmentSession extends Model
{
    /** @use HasFactory<TreatmentSessionFactory> */
    use HasFactory;

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class)->withTrashed();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function treatmentType(): BelongsTo
    {
        return $this->belongsTo(TreatmentType::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TreatmentSessionParticipant::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PatientAttachment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AppointmentKind::class,
            'status' => TreatmentSessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
