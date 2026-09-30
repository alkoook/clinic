<?php

namespace App\Models;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'practitioner_id',
    'room_id',
    'treatment_type_id',
    'rescheduled_from_id',
    'kind',
    'starts_at',
    'ends_at',
    'duration_minutes',
    'status',
    'notes',
    'cancellation_reason',
    'cancelled_at',
    'created_by',
    'updated_by',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

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

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function rescheduledAppointments(): HasMany
    {
        return $this->hasMany(self::class, 'rescheduled_from_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AppointmentParticipant::class);
    }

    public function treatmentSession(): HasOne
    {
        return $this->hasOne(TreatmentSession::class);
    }

    #[Scope]
    protected function upcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())->oldest('starts_at');
    }

    #[Scope]
    protected function blockingTime(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AppointmentStatus::Scheduled,
            AppointmentStatus::Attended,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AppointmentKind::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => AppointmentStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }
}
