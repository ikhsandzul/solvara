<?php

namespace Tests\Feature\Api;

use App\Models\Report;
use App\Models\User;
use App\Services\PriorityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriorityScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_score_saved_on_report_creation(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $citizen = User::factory()->citizen()->create();

        $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'description'  => 'Drainase tersumbat di RT 04 menyebabkan banjir setiap hujan.',
                'occurred_at'  => now()->toIso8601String(),
                'latitude'     => -6.2,
                'longitude'    => 106.8,
            ])->assertStatus(201);

        $report = Report::latest()->first();
        $this->assertNotNull($report->priorityScore);
        $this->assertNotNull($report->priorityScore->rules_version);
        $this->assertEquals(PriorityScoreService::RULES_VERSION, $report->priorityScore->rules_version);
    }

    public function test_priority_breakdown_contains_all_factors(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report  = Report::factory()->create(['user_id' => $citizen->id]);

        $service = app(PriorityScoreService::class);
        $service->calculate($report->load('latestAiAnalysis', 'attachments'));

        $score = $report->refresh()->priorityScore;
        $this->assertNotNull($score);
        $this->assertArrayHasKey('ai_urgency', $score->factor_breakdown_json);
        $this->assertArrayHasKey('age_days', $score->factor_breakdown_json);
        $this->assertArrayHasKey('has_location', $score->factor_breakdown_json);
    }
}
