<?php

namespace Database\Factories;

use App\Enums\CashboxType;
use App\Models\Cashbox;
use App\Models\CashboxTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialWeek;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Expense $expense): void {
            $expense->cashboxTransaction->update([
                'reference_type' => Expense::class,
                'reference_id' => $expense->getKey(),
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
            'expense_category_id' => ExpenseCategory::factory(),
            'cashbox_id' => Cashbox::query()->where('type', CashboxType::Side)->first() ?? Cashbox::factory()->side(),
            'financial_week_id' => FinancialWeek::factory(),
            'amount' => fake()->numberBetween(5, 100) * 1000,
            'occurred_at' => fn (array $attributes) => FinancialWeek::query()
                ->findOrFail($attributes['financial_week_id'])
                ->starts_on
                ->addDay()
                ->setTime(12, 0),
            'description' => fake()->sentence(),
            'affects_weekly_net' => true,
            'idempotency_key' => Str::uuid(),
            'created_by' => User::factory(),
            'cashbox_transaction_id' => fn (array $attributes) => CashboxTransaction::factory()
                ->for(Cashbox::query()->findOrFail($attributes['cashbox_id']))
                ->for(FinancialWeek::query()->findOrFail($attributes['financial_week_id']))
                ->expense()
                ->state([
                    'amount' => $attributes['amount'],
                    'occurred_at' => $attributes['occurred_at'],
                    'created_by' => $attributes['created_by'],
                ])
                ->create()
                ->getKey(),
        ];
    }

    public function excludedFromWeeklyNet(): static
    {
        return $this->state(fn (array $attributes) => ['affects_weekly_net' => false]);
    }
}
