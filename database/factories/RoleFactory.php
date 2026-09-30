<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->lexify('role-????'),
            'label' => fake()->words(2, true),
        ];
    }

    public function withPermissions(int $count = 3): static
    {
        return $this->hasAttached(Permission::factory()->count($count));
    }
}
