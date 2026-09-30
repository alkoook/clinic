<?php

namespace Database\Factories;

use App\Enums\CashboxType;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\CashboxTransfer;
use App\Models\FinancialWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashboxTransfer>
 */
class CashboxTransferFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (CashboxTransfer $transfer): void {
            $transfer->outTransaction->update([
                'reference_type' => CashboxTransfer::class,
                'reference_id' => $transfer->getKey(),
            ]);
            $transfer->inTransaction->update([
                'reference_type' => CashboxTransfer::class,
                'reference_id' => $transfer->getKey(),
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
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'financial_week_id' => FinancialWeek::factory(),
            'occurred_at' => fn (array $attributes) => FinancialWeek::query()
                ->findOrFail($attributes['financial_week_id'])
                ->starts_on
                ->addDays(2)
                ->setTime(12, 0),
            'created_by' => User::factory(),
            'from_cashbox_id' => Cashbox::query()->where('type', CashboxType::Side)->first() ?? Cashbox::factory()->side(),
            'to_cashbox_id' => Cashbox::query()->where('type', CashboxType::Main)->first() ?? Cashbox::factory()->main(),
            'out_transaction_id' => fn (array $attributes) => CashboxTransaction::factory()
                ->for(Cashbox::query()->findOrFail($attributes['from_cashbox_id']))
                ->for(FinancialWeek::query()->findOrFail($attributes['financial_week_id']))
                ->transferOut()
                ->state([
                    'amount' => $attributes['amount'],
                    'occurred_at' => $attributes['occurred_at'],
                    'created_by' => $attributes['created_by'],
                ])
                ->create()
                ->getKey(),
            'in_transaction_id' => fn (array $attributes) => CashboxTransaction::factory()
                ->for(Cashbox::query()->findOrFail($attributes['to_cashbox_id']))
                ->for(FinancialWeek::query()->findOrFail($attributes['financial_week_id']))
                ->transferIn()
                ->state([
                    'amount' => $attributes['amount'],
                    'occurred_at' => $attributes['occurred_at'],
                    'created_by' => $attributes['created_by'],
                ])
                ->create()
                ->getKey(),
            'reason' => fake()->optional()->sentence(),
            'status' => 'completed',
            'idempotency_key' => Str::uuid(),
        ];
    }
}
