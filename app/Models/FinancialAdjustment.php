<?php

namespace App\Models;

use App\Enums\FinancialAdjustmentType;
use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\FinancialAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['practitioner_id', 'financial_week_id', 'refund_id', 'type', 'amount', 'reason', 'idempotency_key', 'occurred_at', 'created_by'])]
class FinancialAdjustment extends Model
{
    /** @use HasFactory<FinancialAdjustmentFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
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
            'type' => FinancialAdjustmentType::class,
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
