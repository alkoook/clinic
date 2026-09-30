<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\CashboxTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'cashbox_id',
    'financial_week_id',
    'type',
    'direction',
    'amount',
    'reference_type',
    'reference_id',
    'description',
    'occurred_at',
    'idempotency_key',
    'created_by',
])]
class CashboxTransaction extends Model
{
    /** @use HasFactory<CashboxTransactionFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function expense(): HasOne
    {
        return $this->hasOne(Expense::class);
    }

    public function outgoingTransfer(): HasOne
    {
        return $this->hasOne(CashboxTransfer::class, 'out_transaction_id');
    }

    public function incomingTransfer(): HasOne
    {
        return $this->hasOne(CashboxTransfer::class, 'in_transaction_id');
    }

    public function practitionerSettlementPayment(): HasOne
    {
        return $this->hasOne(PractitionerSettlementPayment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function mutableFinancialAttributes(): array
    {
        return ['reference_type', 'reference_id'];
    }

    /** @param list<string> $dirtyAttributes */
    protected function permitsFinancialMutation(array $dirtyAttributes): bool
    {
        if ($dirtyAttributes === []) {
            return true;
        }

        return $this->getRawOriginal('reference_type') === null
            && $this->getRawOriginal('reference_id') === null
            && $this->getAttribute('reference_type') !== null
            && $this->getAttribute('reference_id') !== null;
    }
}
