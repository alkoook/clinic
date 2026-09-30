<?php

namespace App\Services;

use App\Models\FinancialAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class FinancialAuditService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        ?Model $auditable,
        ?User $user,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null,
    ): FinancialAuditLog {
        $log = new FinancialAuditLog;
        $log->forceFill([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress ?? $this->requestIpAddress(),
        ]);
        $log->save();

        return $log;
    }

    private function requestIpAddress(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request()->ip();
    }
}
