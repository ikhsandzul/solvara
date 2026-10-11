<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'public_code' => 'SOL-' . date('Y') . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT),
            'user_id' => User::factory()->citizen(),
            'category_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(3),
            'latitude' => fake()->optional(0.7)->latitude(-10, 10),
            'longitude' => fake()->optional(0.7)->longitude(100, 145),
            'address_text' => fake()->address(),
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => 'submitted',
            'analysis_status' => 'queued',
        ];
    }

    public function submitted(): static
    {
        return $this->state(['status' => 'submitted', 'analysis_status' => 'queued']);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress', 'analysis_status' => 'completed']);
    }
}
