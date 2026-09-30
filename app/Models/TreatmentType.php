<?php

namespace App\Models;

use Database\Factories\TreatmentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['specialty_id', 'name', 'description', 'is_group', 'is_active'])]
class TreatmentType extends Model
{
    /** @use HasFactory<TreatmentTypeFactory> */
    use HasFactory, SoftDeletes;

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class)->withTrashed();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function treatmentSessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }

    public function financialAgreements(): HasMany
    {
        return $this->hasMany(FinancialAgreement::class);
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
            'is_group' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
