<?php

namespace App\Services;

use App\Models\FinancialAuditLog;
use Illuminate\Database\Eloquent\Builder;

final class AuditLogQuery
{
    /** @param array<string, mixed> $filters */
    public function build(array $filters): Builder
    {
        $driver = FinancialAuditLog::query()->getConnection()->getDriverName();
        $amountExpression = $driver === 'mysql'
            ? "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(after, '$.amount')), JSON_UNQUOTE(JSON_EXTRACT(after, '$.final_amount')), 0) AS DECIMAL(20,2))"
            : "CAST(COALESCE(json_extract(after, '$.amount'), json_extract(after, '$.final_amount'), 0) AS REAL)";

        return FinancialAuditLog::query()
            ->when(filled($filters['action'] ?? null), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(filled($filters['user_id'] ?? null), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(filled($filters['entity_type'] ?? null), fn (Builder $query) => $query->where('auditable_type', $filters['entity_type']))
            ->when(filled($filters['amount_min'] ?? null), fn (Builder $query) => $query->whereRaw("{$amountExpression} >= ?", [(float) $filters['amount_min']]))
            ->when(filled($filters['amount_max'] ?? null), fn (Builder $query) => $query->whereRaw("{$amountExpression} <= ?", [(float) $filters['amount_max']]))
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(filled($filters['to'] ?? null), fn (Builder $query) => $query->where('created_at', '<=', $filters['to'].' 23:59:59'));
    }
}
