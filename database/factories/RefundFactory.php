<?php

namespace Database\Factories;

use App\Enums\ParticipantPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\CashboxTransaction;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Refund $refund): void {
            $refund->cashboxTransaction->update([
                'reference_type' => Refund::class,
                'reference_id' => $refund->getKey(),
            ]);

            $refund->payment->update([
                'status' => $refund->amount >= $refund->payment->amount
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ]);
            $refund->payment->treatmentSessionParticipant->update([
                'payment_status' => $refund->amount >= $refund->payment->amount
                    ? ParticipantPaymentStatus::Refunded
                    : ParticipantPaymentStatus::PartiallyRefunded,
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
            'payment_id' => Payment::factory(),
            'financial_week_id' => fn (array $attributes) => Payment::query()
                ->findOrFail($attributes['payment_id'])
                ->financial_week_id,
            'amount' => fn (array $attributes) => Payment::query()->findOrFail($attributes['payment_id'])->amount,
            'refunded_at' => fn (array $attributes) => Payment::query()->findOrFail($attributes['payment_id'])->paid_at,
            'cashbox_transaction_id' => fn (array $attributes) => CashboxTransaction::factory()
                ->for(Payment::query()->findOrFail($attributes['payment_id'])->cashboxTransaction->cashbox)
                ->for(Payment::query()->findOrFail($attributes['payment_id'])->financialWeek)
                ->refund()
                ->state([
                    'amount' => $attributes['amount'],
                    'occurred_at' => $attributes['refunded_at'],
                ])
                ->create()
                ->getKey(),
            'reason' => fake()->sentence(),
            'status' => RefundStatus::Completed,
            'after_closed_week' => false,
            'idempotency_key' => Str::uuid(),
            'refunded_by' => null,
        ];
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fn (array $attributes) => max(
                1,
                (int) floor(Payment::query()->findOrFail($attributes['payment_id'])->amount / 2),
            ),
        ]);
    }
}
