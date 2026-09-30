<?php

namespace App\Models;

use App\Enums\SettlementStatus;
use Database\Factories\PractitionerSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'practitioner_id',
    'financial_week_id',
    'opening_balance',
    'earnings_amount',
    'adjustments_amount',
    'total_due',
    'paid_amount',
    'remaining_amount',
    'status',
])]
class PractitionerSettlement extends Model
{
    /** @use HasFactory<PractitionerSettlementFactory> */
    use HasFactory;

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PractitionerSettlementPayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'integer',
            'earnings_amount' => 'integer',
            'adjustments_amount' => 'integer',
            'total_due' => 'integer',
            'paid_amount' => 'integer',
            'remaining_amount' => 'integer',
            'status' => SettlementStatus::class,
        ];
    }
}
