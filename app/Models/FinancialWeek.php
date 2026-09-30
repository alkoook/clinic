<?php

namespace App\Models;

use App\Enums\FinancialWeekStatus;
use Database\Factories\FinancialWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['starts_on', 'ends_on', 'opened_at', 'opened_by', 'status', 'closed_at'])]
class FinancialWeek extends Model
{
    /** @use HasFactory<FinancialWeekFactory> */
    use HasFactory;

    public function cashboxTransactions(): HasMany
    {
        return $this->hasMany(CashboxTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function practitionerEarnings(): HasMany
    {
        return $this->hasMany(PractitionerEarning::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function cashboxTransfers(): HasMany
    {
        return $this->hasMany(CashboxTransfer::class);
    }

    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function weeklyClosing(): HasOne
    {
        return $this->hasOne(WeeklyClosing::class)->whereNull('reopened_at')->latestOfMany();
    }

    public function closing(): HasOne
    {
        return $this->hasOne(WeeklyClosing::class)->whereNull('reopened_at')->latestOfMany();
    }

    public function practitionerSettlements(): HasMany
    {
        return $this->hasMany(PractitionerSettlement::class);
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', FinancialWeekStatus::Open);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'opened_at' => 'datetime',
            'status' => FinancialWeekStatus::class,
            'closed_at' => 'datetime',
        ];
    }
}
