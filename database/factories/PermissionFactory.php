<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('permission-??????'),
            'label' => fake()->words(3, true),
            'group' => fake()->randomElement(['appointments', 'patients', 'finance', 'settings']),
        ];
    }
}
