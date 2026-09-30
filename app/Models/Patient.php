<?php

namespace App\Models;

use App\Enums\PatientSource;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'medical_file_number',
    'full_name',
    'age',
    'gender',
    'marital_status',
    'occupation',
    'companion_name',
    'phone',
    'incoming_hospital_name',
    'source',
    'referring_practitioner_id',
    'current_practitioner_id',
    'was_referred_out',
    'outgoing_hospital_name',
    'referred_out_at',
    'referral_notes',
    'administrative_notes',
    'is_active',
    'created_by',
    'updated_by',
])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, SoftDeletes;

    public function referringPractitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'referring_practitioner_id')->withTrashed();
    }

    public function currentPractitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'current_practitioner_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function appointmentParticipants(): HasMany
    {
        return $this->hasMany(AppointmentParticipant::class);
    }

    public function treatmentSessionParticipants(): HasMany
    {
        return $this->hasMany(TreatmentSessionParticipant::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PatientAttachment::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => PatientSource::class,
            'age' => 'integer',
            'was_referred_out' => 'boolean',
            'referred_out_at' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
