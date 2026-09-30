<?php

namespace App\Models;

use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\WeeklyClosingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'financial_week_id',
    'cashbox_transfer_id',
    'paid_sessions_count',
    'collections',
    'practitioner_shares',
    'expenses',
    'refunds',
    'adjustments',
    'calculated_net',
    'available_cash',
    'reserved_practitioner_liabilities',
    'transferred_surplus',
    'deficit_covered',
    'practitioner_breakdown',
    'closed_at',
    'closed_by',
    'reopened_at',
    'reopened_by',
    'reopen_reason',
])]
class WeeklyClosing extends Model
{
    /** @use HasFactory<WeeklyClosingFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function financialWeek(): BelongsTo
    {
        return $this->belongsTo(FinancialWeek::class);
    }

    public function cashboxTransfer(): BelongsTo
    {
        return $this->belongsTo(CashboxTransfer::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_sessions_count' => 'integer',
            'collections' => 'integer',
            'practitioner_shares' => 'integer',
            'expenses' => 'integer',
            'refunds' => 'integer',
            'adjustments' => 'integer',
            'calculated_net' => 'integer',
            'available_cash' => 'integer',
            'reserved_practitioner_liabilities' => 'integer',
            'transferred_surplus' => 'integer',
            'deficit_covered' => 'integer',
            'practitioner_breakdown' => 'array',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function mutableFinancialAttributes(): array
    {
        return ['reopened_at', 'reopened_by', 'reopen_reason'];
    }
}
