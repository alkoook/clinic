<?php

namespace App\Services;

use App\Enums\ClinicNotificationType;
use App\Enums\FinancialWeekStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\SettlementStatus;
use App\Exceptions\FinancialException;
use App\Models\Cashbox;
use App\Models\FinancialAdjustment;
use App\Models\FinancialWeek;
use App\Models\Payment;
use App\Models\Practitioner;
use App\Models\PractitionerEarning;
use App\Models\PractitionerSettlement;
use App\Models\PractitionerSettlementPayment;
use App\Models\Refund;
use App\Models\User;
use App\Models\WeeklyClosing;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class WeeklyClosingService
{
    public function __construct(
        private readonly FinancialWeekService $financialWeekService,
        private readonly CashboxService $cashboxService,
        private readonly CashboxTransferService $cashboxTransferService,
        private readonly FinancialAuditService $auditService,
        private readonly ClinicNotificationService $notifications,
    ) {}

    /**
     * @return array{
     *     paid_sessions_count: int,
     *     collections: int,
     *     practitioner_shares: int,
     *     expenses: int,
     *     refunds: int,
     *     adjustments: int,
     *     calculated_net: int,
     *     available_cash: int,
     *     reserved_practitioner_liabilities: int,
     *     transferable_surplus: int,
     *     deficit_coverable: int,
     *     practitioner_breakdown: list<array{
     *         practitioner_id: int,
     *         practitioner_name: string,
     *         opening_balance: int,
     *         earnings_amount: int,
     *         adjustments_amount: int,
     *         total_due: int,
     *         paid_amount: int,
     *         remaining_amount: int
     *     }>
     * }
     */
    public function preview(FinancialWeek $week): array
    {
        /** @var WeeklyClosing|null $closing */
        $closing = WeeklyClosing::query()->where('financial_week_id', $week->getKey())->whereNull('reopened_at')->latest('id')->first();

        if ($closing !== null) {
            return $this->fromClosing($closing);
        }

        return $this->calculate($week);
    }

    public function close(FinancialWeek $week, User $actor, string $idempotencyKey): WeeklyClosing
    {
        if (! $actor->hasPermission('cashboxes.manage')) {
            throw FinancialException::permissionDenied('cashboxes.manage');
        }

        $this->cashboxService->assertIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use ($week, $actor, $idempotencyKey): WeeklyClosing {
            /** @var FinancialWeek $lockedWeek */
            $lockedWeek = FinancialWeek::query()
                ->whereKey($week->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            /** @var WeeklyClosing|null $existing */
            $existing = WeeklyClosing::query()
                ->where('financial_week_id', $lockedWeek->getKey())
                ->whereNull('reopened_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $this->financialWeekService->assertOpen($lockedWeek);
            $this->assertNoOlderOpenWeek($lockedWeek);
            $closedAt = now();
            $this->financialWeekService->forDate($closedAt, true);
            $mainCashbox = $this->cashboxService->main();
            $sideCashbox = $this->cashboxService->side();
            Cashbox::query()
                ->whereIn('id', [$mainCashbox->getKey(), $sideCashbox->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $summary = $this->calculate($lockedWeek);
            $this->persistSettlements($lockedWeek, $summary['practitioner_breakdown'], $actor);
            $transfer = null;
            $deficitCovered = 0;

            if ($summary['transferable_surplus'] > 0) {
                $transfer = $this->cashboxTransferService->transfer(
                    $this->cashboxService->main(),
                    $this->cashboxService->side(),
                    $summary['transferable_surplus'],
                    'تحويل فائض الإغلاق الأسبوعي',
                    $actor,
                    $this->transferIdempotencyKey($idempotencyKey, (int) $lockedWeek->getKey()),
                    $closedAt,
                );
            } elseif ($summary['deficit_coverable'] > 0) {
                $deficitCovered = $summary['deficit_coverable'];
                $transfer = $this->cashboxTransferService->transfer(
                    $this->cashboxService->side(),
                    $this->cashboxService->main(),
                    $deficitCovered,
                    'تغطية عجز الإغلاق الأسبوعي',
                    $actor,
                    $this->transferIdempotencyKey($idempotencyKey, (int) $lockedWeek->getKey()),
                    $closedAt,
                );
            }

            $closing = new WeeklyClosing;
            $closing->forceFill([
                'financial_week_id' => $lockedWeek->getKey(),
                'cashbox_transfer_id' => $transfer?->getKey(),
                'paid_sessions_count' => $summary['paid_sessions_count'],
                'collections' => $summary['collections'],
                'practitioner_shares' => $summary['practitioner_shares'],
                'expenses' => $summary['expenses'],
                'refunds' => $summary['refunds'],
                'adjustments' => $summary['adjustments'],
                'calculated_net' => $summary['calculated_net'],
                'available_cash' => $summary['available_cash'],
                'reserved_practitioner_liabilities' => $summary['reserved_practitioner_liabilities'],
                'transferred_surplus' => $summary['transferable_surplus'],
                'deficit_covered' => $deficitCovered,
                'practitioner_breakdown' => $summary['practitioner_breakdown'],
                'closed_at' => $closedAt,
                'closed_by' => $actor->getKey(),
            ]);
            $closing->save();

            $lockedWeek->forceFill([
                'status' => FinancialWeekStatus::Closed->value,
                'closed_at' => $closedAt,
                'ends_on' => $closedAt->toDateString(),
            ]);
            $lockedWeek->save();
            $this->auditService->record(
                'weekly_closing.created',
                $closing,
                $actor,
                after: $closing->getAttributes(),
            );
            $this->notifications->administrative(
                'financial-week:'.$lockedWeek->getKey().':closed',
                ClinicNotificationType::FinancialWeekClosed,
                '📊 تم إغلاق أسبوع مالي',
                'الفترة: '.$lockedWeek->starts_on->format('Y/m/d').' — '.$lockedWeek->ends_on->format('Y/m/d')."\n"
                    .'التحصيلات: '.number_format($summary['collections'])." ل.س\n"
                    .'الصافي المحسوب: '.number_format($summary['calculated_net']).' ل.س',
                route('finance.weekly-closing'),
                ['financial_week_id' => $lockedWeek->getKey(), 'weekly_closing_id' => $closing->getKey()],
            );

            return $closing->refresh();
        }, 3);
    }

    public function reopen(FinancialWeek $week, User $actor, ?string $reason = null): FinancialWeek
    {
        if (! $actor->hasPermission('cashboxes.manage')) {
            throw FinancialException::permissionDenied('cashboxes.manage');
        }

        return DB::transaction(function () use ($week, $actor, $reason): FinancialWeek {
            /** @var FinancialWeek $lockedWeek */
            $lockedWeek = FinancialWeek::query()->whereKey($week->getKey())->lockForUpdate()->firstOrFail();
            /** @var WeeklyClosing|null $closing */
            $closing = WeeklyClosing::query()
                ->where('financial_week_id', $lockedWeek->getKey())
                ->whereNull('reopened_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($closing === null || $lockedWeek->status !== FinancialWeekStatus::Closed) {
                return $lockedWeek;
            }

            $hasPaidSettlements = PractitionerSettlementPayment::query()
                ->whereHas('practitionerSettlement', fn ($query) => $query->where('financial_week_id', $lockedWeek->getKey()))
                ->exists();

            if ($closing->cashbox_transfer_id !== null || $hasPaidSettlements) {
                throw FinancialException::reopeningUnsafe();
            }

            $closing->forceFill([
                'reopened_at' => now(),
                'reopened_by' => $actor->getKey(),
                'reopen_reason' => filled($reason) ? trim((string) $reason) : null,
            ])->save();
            $lockedWeek->forceFill(['status' => FinancialWeekStatus::Open->value, 'closed_at' => null])->save();
            $this->auditService->record('financial_week.reopened', $lockedWeek, $actor, after: [
                'financial_week_id' => $lockedWeek->getKey(),
                'reopened_at' => now(),
                'reason' => $closing->reopen_reason,
            ]);

            return $lockedWeek->refresh();
        }, 3);
    }

    /**
     * @return array{
     *     paid_sessions_count: int,
     *     collections: int,
     *     practitioner_shares: int,
     *     expenses: int,
     *     refunds: int,
     *     adjustments: int,
     *     calculated_net: int,
     *     available_cash: int,
     *     reserved_practitioner_liabilities: int,
     *     transferable_surplus: int,
     *     deficit_coverable: int,
     *     practitioner_breakdown: list<array{
     *         practitioner_id: int,
     *         practitioner_name: string,
     *         opening_balance: int,
     *         earnings_amount: int,
     *         adjustments_amount: int,
     *         total_due: int,
     *         paid_amount: int,
     *         remaining_amount: int
     *     }>
     * }
     */
    private function calculate(FinancialWeek $week): array
    {
        $weekId = (int) $week->getKey();
        $paymentQuery = Payment::query()
            ->where('financial_week_id', $weekId)
            ->where('status', '!=', PaymentStatus::Voided->value);
        $paidSessionsCount = Payment::query()
            ->join(
                'treatment_session_participants',
                'treatment_session_participants.id',
                '=',
                'payments.treatment_session_participant_id',
            )
            ->where('payments.financial_week_id', $weekId)
            ->where('payments.status', '!=', PaymentStatus::Voided->value)
            ->distinct()
            ->count('treatment_session_participants.treatment_session_id');
        $collections = (int) (clone $paymentQuery)->sum('amount');
        $practitionerShares = (int) PractitionerEarning::query()
            ->where('financial_week_id', $weekId)
            ->sum('amount');
        $expenses = (int) DB::table('expenses')
            ->where('financial_week_id', $weekId)
            ->where('affects_weekly_net', true)
            ->sum('amount');
        $refunds = (int) Refund::query()
            ->where('financial_week_id', $weekId)
            ->where('status', RefundStatus::Completed->value)
            ->sum('amount');
        $practitionerAdjustments = (int) FinancialAdjustment::query()
            ->where('financial_week_id', $weekId)
            ->whereNotNull('practitioner_id')
            ->sum('amount');
        $clinicAdjustments = (int) FinancialAdjustment::query()
            ->where('financial_week_id', $weekId)
            ->whereNull('practitioner_id')
            ->sum('amount');
        $netAdjustmentImpact = $clinicAdjustments - $practitionerAdjustments;
        $calculatedNet = $collections
            - $practitionerShares
            - $expenses
            - $refunds
            + $netAdjustmentImpact;
        $breakdown = $this->practitionerBreakdown($week);
        $reservedLiabilities = array_sum(array_map(
            static fn (array $row): int => max(0, $row['remaining_amount']),
            $breakdown,
        ));
        $mainCashbox = $this->cashboxService->main();
        $sideCashbox = $this->cashboxService->side();
        $availableCash = $this->cashboxService->balance($mainCashbox);
        $cashAfterReservations = max(0, $availableCash - $reservedLiabilities);
        $transferableSurplus = min(max(0, $calculatedNet), $cashAfterReservations);
        $deficitCoverable = min(max(0, -$calculatedNet), max(0, $this->cashboxService->balance($sideCashbox)));

        return [
            'paid_sessions_count' => $paidSessionsCount,
            'collections' => $collections,
            'practitioner_shares' => $practitionerShares,
            'expenses' => $expenses,
            'refunds' => $refunds,
            'adjustments' => $netAdjustmentImpact,
            'calculated_net' => $calculatedNet,
            'available_cash' => $availableCash,
            'reserved_practitioner_liabilities' => $reservedLiabilities,
            'transferable_surplus' => $transferableSurplus,
            'deficit_coverable' => $deficitCoverable,
            'practitioner_breakdown' => $breakdown,
        ];
    }

    /**
     * @return list<array{
     *     practitioner_id: int,
     *     practitioner_name: string,
     *     opening_balance: int,
     *     earnings_amount: int,
     *     adjustments_amount: int,
     *     total_due: int,
     *     paid_amount: int,
     *     remaining_amount: int
     * }>
     */
    private function practitionerBreakdown(FinancialWeek $week): array
    {
        $weekId = (int) $week->getKey();
        $startsOn = $this->dateValue($week->getAttribute('starts_on'));
        $earnings = DB::table('practitioner_earnings')
            ->where('financial_week_id', $weekId)
            ->groupBy('practitioner_id')
            ->selectRaw('practitioner_id, SUM(amount) as aggregate_amount')
            ->pluck('aggregate_amount', 'practitioner_id')
            ->map(static fn (mixed $amount): int => (int) $amount);
        $adjustments = DB::table('financial_adjustments')
            ->where('financial_week_id', $weekId)
            ->whereNotNull('practitioner_id')
            ->groupBy('practitioner_id')
            ->selectRaw('practitioner_id, SUM(amount) as aggregate_amount')
            ->pluck('aggregate_amount', 'practitioner_id')
            ->map(static fn (mixed $amount): int => (int) $amount);
        $currentSettlements = PractitionerSettlement::query()
            ->where('financial_week_id', $weekId)
            ->get()
            ->keyBy(static fn (PractitionerSettlement $settlement): int => (int) $settlement->getAttribute('practitioner_id'));
        $priorSettlements = PractitionerSettlement::query()
            ->join('financial_weeks', 'financial_weeks.id', '=', 'practitioner_settlements.financial_week_id')
            ->whereDate('financial_weeks.starts_on', '<', $startsOn)
            ->orderBy('practitioner_settlements.practitioner_id')
            ->orderByDesc('financial_weeks.starts_on')
            ->orderByDesc('practitioner_settlements.id')
            ->select('practitioner_settlements.*')
            ->get()
            ->unique(static fn (PractitionerSettlement $settlement): int => (int) $settlement->getAttribute('practitioner_id'))
            ->keyBy(static fn (PractitionerSettlement $settlement): int => (int) $settlement->getAttribute('practitioner_id'));
        $practitionerIds = collect()
            ->merge($earnings->keys())
            ->merge($adjustments->keys())
            ->merge($currentSettlements->keys())
            ->merge($priorSettlements->filter(
                static fn (PractitionerSettlement $settlement): bool => (int) $settlement->getAttribute('remaining_amount') !== 0,
            )->keys())
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        $practitioners = Practitioner::query()
            ->whereIn('id', $practitionerIds)
            ->get()
            ->keyBy(static fn (Practitioner $practitioner): int => (int) $practitioner->getKey());
        $breakdown = [];

        foreach ($practitionerIds as $practitionerId) {
            /** @var PractitionerSettlement|null $currentSettlement */
            $currentSettlement = $currentSettlements->get($practitionerId);
            /** @var PractitionerSettlement|null $priorSettlement */
            $priorSettlement = $priorSettlements->get($practitionerId);
            /** @var Practitioner|null $practitioner */
            $practitioner = $practitioners->get($practitionerId);
            $openingBalance = $currentSettlement !== null
                ? (int) $currentSettlement->getAttribute('opening_balance')
                : (int) ($priorSettlement?->getAttribute('remaining_amount') ?? 0);
            $earningsAmount = (int) ($earnings->get($practitionerId) ?? 0);
            $adjustmentsAmount = (int) ($adjustments->get($practitionerId) ?? 0);
            $totalDue = $openingBalance + $earningsAmount + $adjustmentsAmount;
            $paidAmount = (int) ($currentSettlement?->getAttribute('paid_amount') ?? 0);

            $breakdown[] = [
                'practitioner_id' => $practitionerId,
                'practitioner_name' => (string) ($practitioner?->getAttribute('name') ?? ''),
                'opening_balance' => $openingBalance,
                'earnings_amount' => $earningsAmount,
                'adjustments_amount' => $adjustmentsAmount,
                'total_due' => $totalDue,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $totalDue - $paidAmount,
            ];
        }

        return $breakdown;
    }

    /**
     * @param  list<array{
     *     practitioner_id: int,
     *     practitioner_name: string,
     *     opening_balance: int,
     *     earnings_amount: int,
     *     adjustments_amount: int,
     *     total_due: int,
     *     paid_amount: int,
     *     remaining_amount: int
     * }>  $breakdown
     */
    private function persistSettlements(FinancialWeek $week, array $breakdown, User $actor): void
    {
        foreach ($breakdown as $row) {
            /** @var PractitionerSettlement|null $existing */
            $existing = PractitionerSettlement::query()
                ->where('practitioner_id', $row['practitioner_id'])
                ->where('financial_week_id', $week->getKey())
                ->lockForUpdate()
                ->first();
            $settlement = $existing ?? new PractitionerSettlement;
            $paidAmount = (int) ($existing?->getAttribute('paid_amount') ?? 0);
            $remainingAmount = $row['total_due'] - $paidAmount;
            $status = match (true) {
                $remainingAmount === 0 => SettlementStatus::Paid,
                $paidAmount > 0 => SettlementStatus::PartiallyPaid,
                default => SettlementStatus::Pending,
            };

            $settlement->forceFill([
                'practitioner_id' => $row['practitioner_id'],
                'financial_week_id' => $week->getKey(),
                'opening_balance' => $row['opening_balance'],
                'earnings_amount' => $row['earnings_amount'],
                'adjustments_amount' => $row['adjustments_amount'],
                'total_due' => $row['total_due'],
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'status' => $status->value,
            ]);
            $settlement->save();

            if ($existing === null) {
                $this->auditService->record(
                    'practitioner_settlement.created',
                    $settlement,
                    $actor,
                    after: $settlement->getAttributes(),
                );
            }
        }
    }

    /**
     * @return array{
     *     paid_sessions_count: int,
     *     collections: int,
     *     practitioner_shares: int,
     *     expenses: int,
     *     refunds: int,
     *     adjustments: int,
     *     calculated_net: int,
     *     available_cash: int,
     *     reserved_practitioner_liabilities: int,
     *     transferable_surplus: int,
     *     deficit_coverable: int,
     *     practitioner_breakdown: list<array<string, mixed>>
     * }
     */
    private function fromClosing(WeeklyClosing $closing): array
    {
        $breakdownValue = $closing->getAttribute('practitioner_breakdown');
        $breakdown = is_array($breakdownValue)
            ? $breakdownValue
            : (json_decode((string) $breakdownValue, true) ?: []);

        return [
            'paid_sessions_count' => (int) $closing->getAttribute('paid_sessions_count'),
            'collections' => (int) $closing->getAttribute('collections'),
            'practitioner_shares' => (int) $closing->getAttribute('practitioner_shares'),
            'expenses' => (int) $closing->getAttribute('expenses'),
            'refunds' => (int) $closing->getAttribute('refunds'),
            'adjustments' => (int) $closing->getAttribute('adjustments'),
            'calculated_net' => (int) $closing->getAttribute('calculated_net'),
            'available_cash' => (int) $closing->getAttribute('available_cash'),
            'reserved_practitioner_liabilities' => (int) $closing->getAttribute('reserved_practitioner_liabilities'),
            'transferable_surplus' => (int) $closing->getAttribute('transferred_surplus'),
            'deficit_coverable' => (int) $closing->getAttribute('deficit_covered'),
            'practitioner_breakdown' => array_values($breakdown),
        ];
    }

    private function transferIdempotencyKey(string $idempotencyKey, int $weekId): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            "clinic:weekly-close:{$weekId}:{$idempotencyKey}",
        )->toString();
    }

    private function assertNoOlderOpenWeek(FinancialWeek $week): void
    {
        $hasOlderOpenWeek = FinancialWeek::query()
            ->whereDate('starts_on', '<', $this->dateValue($week->getAttribute('starts_on')))
            ->where('status', FinancialWeekStatus::Open->value)
            ->orderBy('starts_on')
            ->lockForUpdate()
            ->exists();

        if ($hasOlderOpenWeek) {
            throw FinancialException::olderWeekOpen();
        }
    }

    private function dateValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
