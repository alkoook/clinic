<?php

namespace Database\Factories;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoginActivity>
 */
class LoginActivityFactory extends Factory
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
            'session_identifier' => Str::random(40),
            'login_at' => fake()->dateTimeBetween('-1 month'),
            'logout_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'device_name' => fake()->randomElement(['Desktop', 'Tablet', 'Mobile']),
        ];
    }

    public function loggedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'logout_at' => fake()->dateTimeBetween($attributes['login_at']),
        ]);
    }
}
