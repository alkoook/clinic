<?php

namespace App\Models;

use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\PractitionerSettlementPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['practitioner_settlement_id', 'cashbox_transaction_id', 'amount', 'paid_at', 'idempotency_key', 'paid_by'])]
class PractitionerSettlementPayment extends Model
{
    /** @use HasFactory<PractitionerSettlementPaymentFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function practitionerSettlement(): BelongsTo
    {
        return $this->belongsTo(PractitionerSettlement::class);
    }

    public function cashboxTransaction(): BelongsTo
    {
        return $this->belongsTo(CashboxTransaction::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
