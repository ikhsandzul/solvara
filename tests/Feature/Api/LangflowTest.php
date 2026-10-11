<?php

namespace Tests\Feature\Api;

use App\Jobs\AnalyzeReportJob;
use App\Models\AiAnalysis;
use App\Models\Report;
use App\Models\User;
use App\Services\LangflowClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LangflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_stores_completed_analysis_on_success(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report  = Report::factory()->create(['user_id' => $citizen->id]);

        // Fake Langflow response in expected schema.
        $fakeNormalized = [
            'flow_version'             => 'test-session',
            'provider'                 => 'ibm',
            'model_id'                 => 'granite-3b',
            'category_suggested'       => 'road_damage',
            'summary'                  => 'Jalan berlubang di lokasi tersebut.',
            'keywords_json'            => ['jalan', 'berlubang'],
            'urgency_suggestion'       => 'high',
            'confidence_band'          => 'medium',
            'related_report_ids_json'  => [],
            'pattern_insight'          => null,
            'recommended_next_step'    => 'Segera perbaiki aspal.',
            'missing_information_json' => [],
            'needs_human_review'       => false,
        ];

        $mockClient = $this->createMock(LangflowClient::class);
        $mockClient->method('run')->willReturn(['mock_raw' => true]);
        $mockClient->method('normalize')->willReturn($fakeNormalized);

        $this->app->instance(LangflowClient::class, $mockClient);

        $job = new AnalyzeReportJob($report->id);
        $job->handle($mockClient, app(\App\Services\PriorityScoreService::class));

        $this->assertDatabaseHas('ai_analyses', [
            'report_id'          => $report->id,
            'status'             => 'completed',
            'urgency_suggestion' => 'high',
        ]);

        $report->refresh();
        $this->assertEquals('completed', $report->analysis_status);
    }

    public function test_job_marks_failed_on_langflow_error(): void
    {
        $citizen = User::factory()->citizen()->create();
        $report  = Report::factory()->create(['user_id' => $citizen->id]);

        // Pre-create queued analysis to simulate idempotent start.
        AiAnalysis::create(['report_id' => $report->id, 'status' => 'queued']);

        $mockClient = $this->createMock(LangflowClient::class);
        $mockClient->method('run')->willThrowException(new \RuntimeException('Langflow down'));

        $this->app->instance(LangflowClient::class, $mockClient);

        // Simulate exhausted retries by calling handle with tries reached.
        $job = new class($report->id) extends AnalyzeReportJob {
            public int $tries = 1;
            public function attempts(): int { return 1; }
        };

        $job->handle($mockClient, app(\App\Services\PriorityScoreService::class));

        $this->assertDatabaseHas('ai_analyses', [
            'report_id' => $report->id,
            'status'    => 'failed',
            'error_code'=> 'MAX_RETRIES_EXCEEDED',
        ]);

        $report->refresh();
        $this->assertEquals('failed', $report->analysis_status);
        // Report itself must still exist.
        $this->assertDatabaseHas('reports', ['id' => $report->id]);
    }

    public function test_report_creation_does_not_wait_for_ai(): void
    {
        Queue::fake();

        $citizen = User::factory()->citizen()->create();

        $response = $this->actingAs($citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'description' => 'Jalan rusak parah di depan SD Maju Bersama kota ini.',
                'occurred_at' => now()->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.analysis_status', 'queued');

        Queue::assertPushed(AnalyzeReportJob::class);
    }

    public function test_job_is_idempotent_no_duplicate_completed_records(): void
    {
        $citizen  = User::factory()->citizen()->create();
        $report   = Report::factory()->create(['user_id' => $citizen->id]);

        // Pre-existing completed analysis.
        AiAnalysis::create(['report_id' => $report->id, 'status' => 'completed']);

        $mockClient = $this->createMock(LangflowClient::class);
        $mockClient->expects($this->never())->method('run');

        $job = new AnalyzeReportJob($report->id);
        $job->handle($mockClient, app(\App\Services\PriorityScoreService::class));

        // Should still only have 1 analysis record.
        $this->assertEquals(1, AiAnalysis::where('report_id', $report->id)->count());
    }
}
