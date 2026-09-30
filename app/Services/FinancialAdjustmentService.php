<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\FinancialAdjustmentType;
use App\Exceptions\FinancialException;
use App\Models\FinancialAdjustment;
use App\Models\FinancialWeek;
use App\Models\Practitioner;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class FinancialAdjustmentService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly CashboxService $cashboxService,
        private readonly FinancialAuditService $auditService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    public function record(
        FinancialWeek $week,
        ?Practitioner $practitioner,
        int $amount,
        ?string $reason,
        User $actor,
        string $idempotencyKey,
        ?CarbonInterface $occurredAt = null,
    ): FinancialAdjustment {
        if (! $actor->hasPermission('cashboxes.manage')) {
            throw FinancialException::permissionDenied('cashboxes.manage');
        }

        if ($amount === 0) {
            throw FinancialException::invalidAmount('Adjustment amount cannot be zero.');
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);

        try {
            return DB::transaction(function () use ($week, $practitioner, $amount, $reason, $actor, $idempotencyKey, $occurredAt): FinancialAdjustment {
                $existing = FinancialAdjustment::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $matches = (int) $existing->practitioner_id === (int) $practitioner?->getKey()
                        && (int) $existing->financial_week_id === (int) $week->getKey()
                        && $existing->amount === $amount
                        && $existing->reason === $reason;

                    if (! $matches) {
                        throw FinancialException::duplicateOperation();
                    }

                    return $existing;
                }

                $lockedWeek = FinancialWeek::query()->whereKey($week->getKey())->lockForUpdate()->firstOrFail();
                $this->financialWeekService->assertOpen($lockedWeek);
                $lockedPractitioner = $practitioner === null
                    ? null
                    : Practitioner::query()->withTrashed()->whereKey($practitioner->getKey())->lockForUpdate()->firstOrFail();
                $adjustment = FinancialAdjustment::query()->create([
                    'practitioner_id' => $lockedPractitioner?->getKey(),
                    'financial_week_id' => $lockedWeek->getKey(),
                    'refund_id' => null,
                    'type' => FinancialAdjustmentType::ManualCorrection,
                    'amount' => $amount,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                    'occurred_at' => $occurredAt ?? now(),
                    'created_by' => $actor->getKey(),
                ]);
                $this->auditService->record(
                    'financial_adjustment.created',
                    $adjustment,
                    $actor,
                    after: $adjustment->getAttributes(),
                );
                $this->notifications->financial(
                    'financial-adjustment:'.$adjustment->getKey().':created',
                    ClinicNotificationType::PractitionerAdjustmentCreated,
                    '🧮 تم تسجيل تعديل مالي',
                    ($lockedPractitioner ? "المعالج: {$lockedPractitioner->name}\n" : 'تعديل يخص العيادة'."\n")
                        .'القيمة: '.number_format($amount).' ل.س'
                        .($reason ? "\n".'السبب: '.$reason : ''),
                    route('finance.weekly-closing'),
                    ['financial_adjustment_id' => $adjustment->getKey(), 'amount' => $amount],
                    $lockedPractitioner,
                    $lockedPractitioner ? '🧮 تعديل على مستحقاتك' : null,
                    $lockedPractitioner ? 'القيمة: '.number_format($amount).' ل.س'.($reason ? "\n".'السبب: '.$reason : '') : null,
                );

                return $adjustment;
            }, 3);
        } catch (QueryException $exception) {
            $existing = FinancialAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }
    }
}
