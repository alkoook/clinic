<?php

namespace Database\Factories;

use App\Enums\CashboxType;
use App\Models\Cashbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cashbox>
 */
class CashboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CashboxType::Side,
            'name' => 'Side cashbox',
            'is_active' => true,
        ];
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashboxType::Main,
            'name' => 'Main cashbox',
        ]);
    }

    public function side(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CashboxType::Side,
            'name' => 'Side cashbox',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
