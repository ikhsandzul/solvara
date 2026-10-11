<?php

namespace Database\Factories;

use App\Models\ReportSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSubmission>
 */
class ReportSubmissionFactory extends Factory
{
    protected $model = ReportSubmission::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory()->citizen(),
            'description' => fake()->paragraph(2) . ' ' . fake()->sentence(),
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'received_at' => now(),
            'status'      => 'received',
            'expires_at'  => now()->addHours(24),
        ];
    }
}
