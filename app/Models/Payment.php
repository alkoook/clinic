<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'treatment_session_participant_id',
    'financial_week_id',
    'cashbox_transaction_id',
    'amount',
    'method',
    'status',
    'paid_at',
    'idempotency_key',
    'received_by',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function treatmentSessionParticipant(): BelongsTo
    {
        return $this->belongsTo(TreatmentSessionParticipant::class);
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function cashboxTransaction(): BelongsTo
    {
        return $this->belongsTo(CashboxTransaction::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function practitionerEarning(): HasOne
    {
        return $this->hasOne(PractitionerEarning::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function mutableFinancialAttributes(): array
    {
        return ['status'];
    }

    /** @param list<string> $dirtyAttributes */
    protected function permitsFinancialMutation(array $dirtyAttributes): bool
    {
        if ($dirtyAttributes === []) {
            return true;
        }

        $originalStatus = (string) $this->getRawOriginal('status');
        $status = $this->getAttribute('status');
        $newStatus = $status instanceof PaymentStatus ? $status->value : (string) $status;
        $transitions = [
            PaymentStatus::Paid->value => [PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value],
            PaymentStatus::PartiallyRefunded->value => [PaymentStatus::Refunded->value],
        ];

        return array_key_exists($originalStatus, $transitions)
            && in_array($newStatus, $transitions[$originalStatus], true);
    }
}
