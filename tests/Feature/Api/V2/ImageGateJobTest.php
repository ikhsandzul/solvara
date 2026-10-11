<?php

namespace Tests\Feature\Api\V2;

use App\Jobs\RunImageGateJob;
use App\Jobs\RunDuplicateIntelligenceJob;
use App\Models\AiAnalysisRun;
use App\Models\Category;
use App\Models\ReportSubmission;
use App\Models\User;
use App\Services\FinalizeReportSubmissionService;
use App\Services\LangflowImageGateClient;
use App\Services\LangflowResponseNormalizer;
use App\Services\PrivateReportMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageGateJobTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citizen = User::factory()->citizen()->create();
        Category::create(['code' => 'road_damage', 'name' => 'Jalan Rusak', 'is_active' => true]);
    }

    private function makeSubmission(string $status = 'queued_for_moderation'): ReportSubmission
    {
        return ReportSubmission::create([
            'user_id'                   => $this->citizen->id,
            'description'               => 'Jalan berlubang dalam.',
            'occurred_at'               => now(),
            'received_at'               => now(),
            'status'                    => $status,
            'temporary_attachment_path' => 'submissions/quarantine/fake.jpg',
            'detected_mime'             => 'image/jpeg',
            'size_bytes'                => 102400,
            'expires_at'                => now()->addHours(24),
        ]);
    }

    public function test_approved_submission_routes_to_duplicate_check(): void
    {
        Queue::fake(); // prevent RunDuplicateIntelligenceJob from running synchronously
        Storage::fake('private');
        $submission = $this->makeSubmission();

        $mockClient = $this->createMock(LangflowImageGateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('run')->willReturn(['mock' => true]);

        $mockNormalizer = $this->createMock(LangflowResponseNormalizer::class);
        $mockNormalizer->method('normalizeImageGate')->willReturn([
            'moderation_decision' => 'approved',
            'is_civic_issue'      => true,
            'suggested_category'  => 'road_damage',
            'image_description'   => 'Permukaan jalan berlubang',
            'description_match'   => 'match',
            'confidence_band'     => 'high',
            'reasons'             => ['Gambar relevan'],
            'needs_human_review'  => false,
            'flow_version'        => 'test_v1',
        ]);

        $this->app->instance(LangflowImageGateClient::class, $mockClient);
        $this->app->instance(LangflowResponseNormalizer::class, $mockNormalizer);

        $job = new RunImageGateJob($submission->id);
        $job->handle(
            $mockClient,
            $mockNormalizer,
            app(FinalizeReportSubmissionService::class),
            app(PrivateReportMediaStorage::class)
        );

        $submission->refresh();
        $this->assertEquals('queued_for_duplicate_check', $submission->status);

        $this->assertDatabaseHas('report_moderation_results', [
            'submission_id'       => $submission->id,
            'moderation_decision' => 'approved',
        ]);
    }

    public function test_rejected_submission_does_not_create_report(): void
    {
        Storage::fake('private');
        $submission = $this->makeSubmission();

        $mockClient = $this->createMock(LangflowImageGateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('run')->willReturn(['mock' => true]);

        $mockNormalizer = $this->createMock(LangflowResponseNormalizer::class);
        $mockNormalizer->method('normalizeImageGate')->willReturn([
            'moderation_decision' => 'rejected',
            'is_civic_issue'      => false,
            'suggested_category'  => null,
            'image_description'   => null,
            'description_match'   => 'mismatch',
            'confidence_band'     => 'high',
            'reasons'             => [],
            'needs_human_review'  => false,
            'flow_version'        => 'test_v1',
        ]);

        $this->app->instance(LangflowImageGateClient::class, $mockClient);
        $this->app->instance(LangflowResponseNormalizer::class, $mockNormalizer);

        $job = new RunImageGateJob($submission->id);
        $job->handle(
            $mockClient,
            $mockNormalizer,
            app(FinalizeReportSubmissionService::class),
            app(PrivateReportMediaStorage::class)
        );

        $submission->refresh();
        $this->assertEquals('rejected', $submission->status);
        // No report should be created.
        $this->assertDatabaseEmpty('reports');
    }

    public function test_langflow_not_configured_routes_to_manual_review(): void
    {
        $submission = $this->makeSubmission();

        $mockClient = $this->createMock(LangflowImageGateClient::class);
        $mockClient->method('isConfigured')->willReturn(false);

        $this->app->instance(LangflowImageGateClient::class, $mockClient);

        $job = new RunImageGateJob($submission->id);
        $job->handle(
            $mockClient,
            app(LangflowResponseNormalizer::class),
            app(FinalizeReportSubmissionService::class),
            app(PrivateReportMediaStorage::class)
        );

        $submission->refresh();
        $this->assertEquals('manual_review', $submission->status);
        $this->assertDatabaseEmpty('reports');
    }

    public function test_invalid_flow_response_marks_failed_when_retries_exhausted(): void
    {
        $submission = $this->makeSubmission();

        $mockClient = $this->createMock(LangflowImageGateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('run')->willThrowException(new \RuntimeException('Langflow down'));

        $this->app->instance(LangflowImageGateClient::class, $mockClient);

        $job = new class($submission->id) extends RunImageGateJob {
            public int $tries = 1;
            public function attempts(): int { return 1; }
        };

        $job->handle(
            $mockClient,
            app(LangflowResponseNormalizer::class),
            app(FinalizeReportSubmissionService::class),
            app(PrivateReportMediaStorage::class)
        );

        $submission->refresh();
        $this->assertEquals('processing_failed', $submission->status);
        $this->assertDatabaseEmpty('reports');
    }

    public function test_manual_review_output_does_not_auto_approve(): void
    {
        $submission = $this->makeSubmission();

        $mockClient = $this->createMock(LangflowImageGateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('run')->willReturn([]);

        $mockNormalizer = $this->createMock(LangflowResponseNormalizer::class);
        $mockNormalizer->method('normalizeImageGate')->willReturn([
            'moderation_decision' => 'manual_review',
            'is_civic_issue'      => true,
            'suggested_category'  => 'road_damage',
            'image_description'   => null,
            'description_match'   => 'uncertain',
            'confidence_band'     => 'low',
            'reasons'             => [],
            'needs_human_review'  => true,
            'flow_version'        => 'test_v1',
        ]);

        $this->app->instance(LangflowImageGateClient::class, $mockClient);
        $this->app->instance(LangflowResponseNormalizer::class, $mockNormalizer);

        $job = new RunImageGateJob($submission->id);
        $job->handle(
            $mockClient,
            $mockNormalizer,
            app(FinalizeReportSubmissionService::class),
            app(PrivateReportMediaStorage::class)
        );

        $submission->refresh();
        $this->assertEquals('manual_review', $submission->status);
        $this->assertDatabaseEmpty('reports');
    }
}
