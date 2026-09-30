<?php

namespace App\Models;

use Database\Factories\PractitionerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'phone', 'telegram_chat_id', 'contact_data', 'administrative_notes', 'is_active'])]
class Practitioner extends Model
{
    /** @use HasFactory<PractitionerFactory> */
    use HasFactory, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class);
    }

    public function referredPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'referring_practitioner_id');
    }

    public function currentPatients(): HasMany
    {
        return $this->hasMany(Patient::class, 'current_practitioner_id');
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'current_practitioner_id');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(PractitionerAvailability::class);
    }

    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(PractitionerAvailabilityException::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function treatmentSessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }

    public function sourcedSessionParticipants(): HasMany
    {
        return $this->hasMany(TreatmentSessionParticipant::class, 'source_practitioner_id');
    }

    public function financialAgreements(): HasMany
    {
        return $this->hasMany(FinancialAgreement::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PractitionerEarning::class);
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(PractitionerSettlement::class);
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
            'contact_data' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
