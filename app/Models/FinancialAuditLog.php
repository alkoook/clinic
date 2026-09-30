<?php

namespace App\Models;

use App\Models\Concerns\PreventsFinancialRecordMutation;
use Database\Factories\FinancialAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'action', 'auditable_type', 'auditable_id', 'before', 'after', 'ip_address'])]
class FinancialAuditLog extends Model
{
    /** @use HasFactory<FinancialAuditLogFactory> */
    use HasFactory, PreventsFinancialRecordMutation;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
