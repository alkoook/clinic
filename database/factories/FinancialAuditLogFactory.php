<?php

namespace Database\Factories;

use App\Models\FinancialAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAuditLog>
 */
class FinancialAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'refunded', 'closed']),
            'auditable_type' => null,
            'auditable_id' => null,
            'before' => null,
            'after' => ['status' => 'updated'],
            'ip_address' => fake()->ipv4(),
        ];
    }
}
