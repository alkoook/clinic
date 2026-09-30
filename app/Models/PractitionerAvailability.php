<?php

namespace App\Models;

use Database\Factories\PractitionerAvailabilityFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'practitioner_id',
    'day_of_week',
    'start_time',
    'end_time',
    'effective_from',
    'effective_to',
    'is_active',
])]
class PractitionerAvailability extends Model
{
    /** @use HasFactory<PractitionerAvailabilityFactory> */
    use HasFactory;

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
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
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('effective_from')
                ->orWhereDate('effective_from', '<=', $date))
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
            'day_of_week' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
