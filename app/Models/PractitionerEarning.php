<?php

namespace App\Models;

use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\PractitionerEarningFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id',
    'treatment_session_participant_id',
    'practitioner_id',
    'financial_week_id',
    'gross_amount',
    'practitioner_percentage',
    'amount',
    'status',
    'earned_at',
])]
class PractitionerEarning extends Model
{
    /** @use HasFactory<PractitionerEarningFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function treatmentSessionParticipant(): BelongsTo
    {
        return $this->belongsTo(TreatmentSessionParticipant::class);
    }

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_amount' => 'integer',
            'practitioner_percentage' => 'decimal:2',
            'amount' => 'integer',
            'earned_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function mutableFinancialAttributes(): array
    {
        return ['amount', 'status'];
    }

    /** @param list<string> $dirtyAttributes */
    protected function permitsFinancialMutation(array $dirtyAttributes): bool
    {
        if ($dirtyAttributes === []) {
            return true;
        }

        $originalStatus = (string) $this->getRawOriginal('status');
        $newStatus = (string) $this->getAttribute('status');
        $validStatus = match ($originalStatus) {
            'accrued' => in_array($newStatus, ['partially_refunded', 'refunded'], true),
            'partially_refunded' => $newStatus === 'refunded',
            default => false,
        };

        return $validStatus && (int) $this->getAttribute('amount') <= (int) $this->getRawOriginal('amount');
    }
}
