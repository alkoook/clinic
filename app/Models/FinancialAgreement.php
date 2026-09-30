<?php

namespace App\Models;

use Database\Factories\FinancialAgreementFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'practitioner_id',
    'practitioner_percentage',
    'effective_from',
    'effective_to',
    'is_active',
    'notes',
    'created_by',
])]
class FinancialAgreement extends Model
{
    /** @use HasFactory<FinancialAgreementFactory> */
    use HasFactory, SoftDeletes;

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessionParticipants(): HasMany
    {
        return $this->hasMany(TreatmentSessionParticipant::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    protected function effectiveOn(Builder $query, DateTimeInterface|string $date): Builder
    {
        return $query
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $date));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legacy_context' => 'array',
            'practitioner_percentage' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
