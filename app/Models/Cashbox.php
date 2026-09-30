<?php

namespace App\Models;

use App\Enums\CashboxType;
use Database\Factories\CashboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'is_active'])]
class Cashbox extends Model
{
    /** @use HasFactory<CashboxFactory> */
    use HasFactory;

    public function transactions(): HasMany
    {
        return $this->hasMany(CashboxTransaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(CashboxTransfer::class, 'from_cashbox_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(CashboxTransfer::class, 'to_cashbox_id');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashboxType::class,
            'is_active' => 'boolean',
        ];
    }
}
