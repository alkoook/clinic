<?php

namespace App\Models;

use App\Enums\RefundStatus;
use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_id',
    'financial_week_id',
    'cashbox_transaction_id',
    'amount',
    'reason',
    'status',
    'after_closed_week',
    'refunded_at',
    'idempotency_key',
    'refunded_by',
])]
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function cashboxTransaction(): BelongsTo
    {
        return $this->belongsTo(CashboxTransaction::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RefundStatus::class,
            'after_closed_week' => 'boolean',
            'refunded_at' => 'datetime',
        ];
    }
}
