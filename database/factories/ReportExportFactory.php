<?php

namespace Database\Factories;

use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'type' => 'patients',
            'filters' => ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')],
            'status' => 'pending',
            'download_name' => 'تقرير_المرضى.xlsx',
        ];
    }
}
