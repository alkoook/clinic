<?php

namespace Database\Factories;

use App\Enums\CashboxType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\FinancialWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashboxTransaction>
 */
class CashboxTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cashbox_id' => Cashbox::query()->where('type', CashboxType::Side)->first() ?? Cashbox::factory()->side(),
            'financial_week_id' => FinancialWeek::factory(),
            'type' => TransactionType::SessionPayment,
            'direction' => TransactionDirection::In,
            'amount' => fake()->numberBetween(20, 150) * 1000,
            'reference_type' => null,
            'reference_id' => null,
            'description' => fake()->optional()->sentence(),
            'occurred_at' => fn (array $attributes) => FinancialWeek::query()
                ->findOrFail($attributes['financial_week_id'])
                ->starts_on
                ->addDay()
                ->setTime(12, 0),
            'idempotency_key' => Str::uuid(),
            'created_by' => User::factory(),
        ];
    }

    public function expense(): static
    {
        return $this->outgoing(TransactionType::Expense);
    }

    public function refund(): static
    {
        return $this->outgoing(TransactionType::Refund);
    }

    public function practitionerSettlement(): static
    {
        return $this->outgoing(TransactionType::PractitionerSettlement);
    }

    public function transferOut(): static
    {
        return $this->outgoing(TransactionType::TransferOut);
    }

    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::TransferIn,
            'direction' => TransactionDirection::In,
        ]);
    }

    private function outgoing(TransactionType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'direction' => TransactionDirection::Out,
        ]);
    }
}
