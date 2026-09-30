<?php

namespace App\Services;

use App\Exceptions\FinancialException;
use App\Models\FinancialAgreement;
use App\Models\Practitioner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FinancialAgreementService
{
    public function __construct(private readonly FinancialAuditService $auditService) {}

    /**
     * @param  array{
     *     practitioner_id: int,
     *     practitioner_percentage: int|string,
     *     effective_from: CarbonInterface|DateTimeInterface|string,
     *     effective_to?: CarbonInterface|DateTimeInterface|string|null,
     *     is_active?: bool,
     *     notes?: string|null
     * }  $data
     */
    public function create(array $data, User $actor): FinancialAgreement
    {
        if (! $actor->hasPermission('financial.agreements.manage')) {
            throw FinancialException::permissionDenied('financial.agreements.manage');
        }

        return DB::transaction(function () use ($data, $actor): FinancialAgreement {
            $attributes = $this->attributes($data);
            $this->lockAgreementResources($attributes['practitioner_id']);
            $this->assertNoOverlap($attributes);

            $agreement = new FinancialAgreement;
            $agreement->forceFill($attributes + ['created_by' => $actor->getKey()]);
            $agreement->save();

            $this->auditService->record(
                'financial_agreement.created',
                $agreement,
                $actor,
                after: $agreement->getAttributes(),
            );

            return $agreement->refresh();
        }, 3);
    }

    /**
     * @param  array{
     *     practitioner_id?: int,
     *     practitioner_percentage?: int|string,
     *     effective_from?: CarbonInterface|DateTimeInterface|string,
     *     effective_to?: CarbonInterface|DateTimeInterface|string|null,
     *     is_active?: bool,
     *     notes?: string|null
     * }  $data
     */
    public function update(FinancialAgreement $agreement, array $data, User $actor): FinancialAgreement
    {
        if (! $actor->hasPermission('financial.agreements.manage')) {
            throw FinancialException::permissionDenied('financial.agreements.manage');
        }

        return DB::transaction(function () use ($agreement, $data, $actor): FinancialAgreement {
            $practitionerId = (int) ($data['practitioner_id'] ?? $agreement->getAttribute('practitioner_id'));
            $this->lockAgreementResources($practitionerId);

            /** @var FinancialAgreement $lockedAgreement */
            $lockedAgreement = FinancialAgreement::query()
                ->whereKey($agreement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $before = $lockedAgreement->getAttributes();
            $attributes = $this->attributes(array_merge([
                'practitioner_id' => $lockedAgreement->getAttribute('practitioner_id'),
                'practitioner_percentage' => $lockedAgreement->getAttribute('practitioner_percentage'),
                'effective_from' => $lockedAgreement->getAttribute('effective_from'),
                'effective_to' => $lockedAgreement->getAttribute('effective_to'),
                'is_active' => $lockedAgreement->getAttribute('is_active'),
                'notes' => $lockedAgreement->getAttribute('notes'),
            ], $data));

            $this->assertNoOverlap($attributes, (int) $lockedAgreement->getKey());
            $lockedAgreement->forceFill($attributes);
            $lockedAgreement->save();

            $this->auditService->record(
                'financial_agreement.updated',
                $lockedAgreement,
                $actor,
                before: $before,
                after: $lockedAgreement->getAttributes(),
            );

            return $lockedAgreement->refresh();
        }, 3);
    }

    public function delete(FinancialAgreement $agreement, User $actor): void
    {
        if (! $actor->hasPermission('financial.agreements.manage')) {
            throw FinancialException::permissionDenied('financial.agreements.manage');
        }

        DB::transaction(function () use ($agreement, $actor): void {
            $lockedAgreement = FinancialAgreement::query()->whereKey($agreement->getKey())->lockForUpdate()->firstOrFail();
            $before = $lockedAgreement->getAttributes();
            $lockedAgreement->forceFill(['is_active' => false])->save();
            $lockedAgreement->delete();
            $this->auditService->record('financial_agreement.archived', $lockedAgreement, $actor, before: $before, after: $lockedAgreement->getAttributes());
        }, 3);
    }

    public function effectiveFor(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $effectiveAt,
    ): FinancialAgreement {
        return $this->findEffective($practitioner, $effectiveAt)
            ?? throw FinancialException::missingAgreement();
    }

    public function findEffective(
        Practitioner|int $practitioner,
        CarbonInterface|DateTimeInterface|string $effectiveAt,
    ): ?FinancialAgreement {
        $practitionerId = $practitioner instanceof Practitioner ? (int) $practitioner->getKey() : $practitioner;
        $date = $this->date($effectiveAt)->toDateString();

        /** @var FinancialAgreement|null $agreement */
        $agreement = FinancialAgreement::query()
            ->where('practitioner_id', $practitionerId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $agreement;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     practitioner_id: int,
     *     practitioner_percentage: string,
     *     effective_from: string,
     *     effective_to: string|null,
     *     is_active: bool,
     *     notes: string|null
     * }
     */
    private function attributes(array $data): array
    {
        $effectiveFrom = $this->date($data['effective_from'])->startOfDay();
        $effectiveTo = isset($data['effective_to']) && $data['effective_to'] !== null
            ? $this->date($data['effective_to'])->startOfDay()
            : null;

        if ($effectiveTo !== null && $effectiveTo->lessThan($effectiveFrom)) {
            throw FinancialException::overlappingAgreement();
        }

        return [
            'practitioner_id' => (int) $data['practitioner_id'],
            'practitioner_percentage' => $this->percentage($data['practitioner_percentage']),
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
        ];
    }

    /**
     * @param  array{
     *     practitioner_id: int,
     *     practitioner_percentage: string,
     *     effective_from: string,
     *     effective_to: string|null,
     *     is_active: bool,
     *     notes: string|null
     * }  $attributes
     */
    private function assertNoOverlap(array $attributes, ?int $exceptId = null): void
    {
        if (! $attributes['is_active']) {
            return;
        }

        $query = FinancialAgreement::query()
            ->where('practitioner_id', $attributes['practitioner_id'])
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $attributes['effective_to'] ?? '9999-12-31')
            ->where(function (Builder $query) use ($attributes): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $attributes['effective_from']);
            })
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->where('id', '!=', $exceptId))
            ->lockForUpdate();

        if ($query->first() !== null) {
            throw FinancialException::overlappingAgreement();
        }
    }

    private function lockAgreementResources(int $practitionerId): void
    {
        Practitioner::query()->whereKey($practitionerId)->lockForUpdate()->firstOrFail();
    }

    private function percentage(mixed $value): string
    {
        $normalized = trim((string) $value);

        if (! preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $normalized)) {
            throw FinancialException::invalidPercentage();
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $basisPoints = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw FinancialException::invalidPercentage();
        }

        return sprintf('%d.%02d', intdiv($basisPoints, 100), $basisPoints % 100);
    }

    private function date(CarbonInterface|DateTimeInterface|string $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone((string) config('app.timezone'));
        }

        return CarbonImmutable::parse($value, (string) config('app.timezone'));
    }
}
