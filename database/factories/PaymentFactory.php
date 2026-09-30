<?php

namespace Database\Factories;

use App\Enums\ParticipantPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CashboxTransaction;
use App\Models\FinancialWeek;
use App\Models\Payment;
use App\Models\TreatmentSessionParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Payment $payment): void {
            $payment->cashboxTransaction->update([
                'reference_type' => Payment::class,
                'reference_id' => $payment->getKey(),
            ]);

            $payment->treatmentSessionParticipant->update([
                'payment_status' => ParticipantPaymentStatus::Paid,
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
            'treatment_session_participant_id' => TreatmentSessionParticipant::factory(),
            'financial_week_id' => FinancialWeek::factory(),
            'amount' => fn (array $attributes) => TreatmentSessionParticipant::query()
                ->findOrFail($attributes['treatment_session_participant_id'])
                ->final_amount,
            'paid_at' => fn (array $attributes) => FinancialWeek::query()
                ->findOrFail($attributes['financial_week_id'])
                ->starts_on
                ->addDay()
                ->setTime(12, 0),
            'cashbox_transaction_id' => fn (array $attributes) => CashboxTransaction::factory()
                ->for(FinancialWeek::query()->findOrFail($attributes['financial_week_id']))
                ->state([
                    'amount' => $attributes['amount'],
                    'occurred_at' => $attributes['paid_at'],
                ])
                ->create()
                ->getKey(),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'idempotency_key' => Str::uuid(),
            'received_by' => User::factory(),
        ];
    }
}
