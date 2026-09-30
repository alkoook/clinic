<?php

namespace Database\Factories;

use App\Enums\CashboxType;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\PractitionerSettlement;
use App\Models\PractitionerSettlementPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PractitionerSettlementPayment>
 */
class PractitionerSettlementPaymentFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (PractitionerSettlementPayment $payment): void {
            $payment->cashboxTransaction->update([
                'reference_type' => PractitionerSettlementPayment::class,
                'reference_id' => $payment->getKey(),
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'practitioner_settlement_id' => PractitionerSettlement::factory(),
            'amount' => fn (array $attributes) => max(
                1,
                PractitionerSettlement::query()->findOrFail($attributes['practitioner_settlement_id'])->remaining_amount,
            ),
            'paid_at' => fn (array $attributes) => PractitionerSettlement::query()
                ->findOrFail($attributes['practitioner_settlement_id'])
                ->financialWeek
                ->starts_on
                ->addDay()
                ->setTime(12, 0),
            'paid_by' => User::factory(),
            'cashbox_transaction_id' => fn (array $attributes) => CashboxTransaction::factory()
                ->for(Cashbox::query()->where('type', CashboxType::Side)->first() ?? Cashbox::factory()->side())
                ->for(PractitionerSettlement::query()
                    ->findOrFail($attributes['practitioner_settlement_id'])
                    ->financialWeek)
                ->practitionerSettlement()
                ->state([
                    'amount' => $attributes['amount'],
                    'occurred_at' => $attributes['paid_at'],
                    'created_by' => $attributes['paid_by'],
                ])
                ->create()
                ->getKey(),
            'idempotency_key' => Str::uuid(),
        ];
    }
}
