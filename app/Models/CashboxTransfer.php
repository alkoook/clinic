<?php

namespace App\Models;

use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\CashboxTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_cashbox_id',
    'to_cashbox_id',
    'financial_week_id',
    'out_transaction_id',
    'in_transaction_id',
    'amount',
    'occurred_at',
    'reason',
    'status',
    'idempotency_key',
    'created_by',
])]
class CashboxTransfer extends Model
{
    /** @use HasFactory<CashboxTransferFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function fromCashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'from_cashbox_id');
    }

    public function toCashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class, 'to_cashbox_id');
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function outTransaction(): BelongsTo
    {
        return $this->belongsTo(CashboxTransaction::class, 'out_transaction_id');
    }

    public function inTransaction(): BelongsTo
    {
        return $this->belongsTo(CashboxTransaction::class, 'in_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
