<?php

namespace Tests\Feature\Api\V2;

use App\Jobs\RunDuplicateIntelligenceJob;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportModerationResult;
use App\Models\ReportSubmission;
use App\Models\User;
use App\Services\FindNearbyReportCandidates;
use App\Services\LangflowDuplicateClient;
use App\Services\LangflowResponseNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateIntelligenceJobTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citizen  = User::factory()->citizen()->create();
        $this->category = Category::create(['code' => 'road_damage', 'name' => 'Jalan Rusak', 'is_active' => true]);
    }

    private function makeSubmission(float $lat = -6.2, float $lng = 106.8): ReportSubmission
    {
        $sub = ReportSubmission::create([
            'user_id'              => $this->citizen->id,
            'description'          => 'Jalan berlubang dalam sekali di sini.',
            'selected_category_id' => $this->category->id,
            'latitude'             => $lat,
            'longitude'            => $lng,
            'occurred_at'          => now(),
            'received_at'          => now(),
            'status'               => 'queued_for_duplicate_check',
            'expires_at'           => now()->addHours(24),
        ]);

        ReportModerationResult::create([
            'submission_id'       => $sub->id,
            'moderation_decision' => 'approved',
            'is_civic_issue'      => true,
            'image_description'   => 'Jalan berlubang terlihat jelas.',
        ]);

        return $sub;
    }

    public function test_no_candidates_routes_to_manual_review(): void
    {
        $submission = $this->makeSubmission();

        $mockClient = $this->createMock(LangflowDuplicateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);

        // Return empty candidates by mocking FindNearbyReportCandidates.
        $mockFinder = $this->createMock(FindNearbyReportCandidates::class);
        $mockFinder->method('find')->willReturn([]);

        $this->app->instance(LangflowDuplicateClient::class, $mockClient);
        $this->app->instance(FindNearbyReportCandidates::class, $mockFinder);

        $job = new RunDuplicateIntelligenceJob($submission->id);
        $job->handle($mockClient, app(LangflowResponseNormalizer::class), $mockFinder);

        $submission->refresh();
        $this->assertEquals('manual_review', $submission->status);
    }

    public function test_possible_duplicate_routes_to_duplicate_review(): void
    {
        $officer = User::factory()->officer()->create();
        $report  = Report::create([
            'public_code' => 'SOL-2026-0001',
            'user_id'     => $this->citizen->id,
            'category_id' => $this->category->id,
            'description' => 'Ada lubang di jalan dekat gerbang sekolah.',
            'latitude'    => -6.2001,
            'longitude'   => 106.8001,
            'occurred_at' => now()->subDays(3),
            'status'      => 'in_progress',
            'analysis_status' => 'completed',
        ]);

        $submission = $this->makeSubmission(-6.2, 106.8);

        // Fake candidate that matches report.
        $fakeCandidate           = new \stdClass();
        $fakeCandidate->id       = $report->id;
        $fakeCandidate->distance_m = 15.0;
        $fakeCandidate->status   = 'in_progress';
        $fakeCandidate->category_id = $this->category->id;
        $fakeCandidate->description = $report->description;
        $fakeCandidate->candidate_type = 'active';

        $mockFinder = $this->createMock(FindNearbyReportCandidates::class);
        $mockFinder->method('find')->willReturn([$fakeCandidate]);

        $mockClient = $this->createMock(LangflowDuplicateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('run')->willReturn(['mock' => true]);

        $mockNormalizer = $this->createMock(LangflowResponseNormalizer::class);
        $mockNormalizer->method('normalizeDuplicate')->willReturn([
            'duplicate_decision' => 'possible_duplicate',
            'matches'            => [
                [
                    'candidate_report_id' => (string) $report->id,
                    'match_type'          => 'same_issue',
                    'confidence_band'     => 'high',
                    'reason'              => 'Sangat mirip.',
                ],
            ],
            'summary'            => 'Ada kandidat duplikat.',
            'needs_human_review' => true,
            'flow_version'       => 'dup_v1',
        ]);

        $this->app->instance(LangflowDuplicateClient::class, $mockClient);
        $this->app->instance(LangflowResponseNormalizer::class, $mockNormalizer);
        $this->app->instance(FindNearbyReportCandidates::class, $mockFinder);

        $job = new RunDuplicateIntelligenceJob($submission->id);
        $job->handle($mockClient, $mockNormalizer, $mockFinder);

        $submission->refresh();
        $this->assertEquals('duplicate_review', $submission->status);

        $this->assertDatabaseHas('report_similarity_matches', [
            'submission_id'       => $submission->id,
            'candidate_report_id' => $report->id,
        ]);
    }

    public function test_hallucinated_candidate_id_is_stripped(): void
    {
        $submission = $this->makeSubmission();

        $report = Report::create([
            'public_code' => 'SOL-2026-0001',
            'user_id'     => $this->citizen->id,
            'category_id' => $this->category->id,
            'description' => 'Jalan rusak parah.',
            'latitude'    => -6.2001,
            'longitude'   => 106.8001,
            'occurred_at' => now(),
            'status'      => 'in_progress',
            'analysis_status' => 'completed',
        ]);

        $fakeCandidate = new \stdClass();
        $fakeCandidate->id = $report->id;
        $fakeCandidate->distance_m = 20.0;
        $fakeCandidate->status = 'in_progress';
        $fakeCandidate->category_id = null;
        $fakeCandidate->description = '';
        $fakeCandidate->candidate_type = 'active';

        $mockFinder = $this->createMock(FindNearbyReportCandidates::class);
        $mockFinder->method('find')->willReturn([$fakeCandidate]);

        $mockClient = $this->createMock(LangflowDuplicateClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('run')->willReturn([]);

        // Normalizer returns a hallucinated ID (999) not in candidate list.
        $mockNormalizer = $this->createMock(LangflowResponseNormalizer::class);
        $mockNormalizer->method('normalizeDuplicate')->willReturn([
            'duplicate_decision' => 'possible_duplicate',
            'matches'            => [], // hallucinated ID stripped by normalizer
            'summary'            => null,
            'needs_human_review' => true,
            'flow_version'       => 'dup_v1',
        ]);

        $this->app->instance(LangflowDuplicateClient::class, $mockClient);
        $this->app->instance(LangflowResponseNormalizer::class, $mockNormalizer);
        $this->app->instance(FindNearbyReportCandidates::class, $mockFinder);

        $job = new RunDuplicateIntelligenceJob($submission->id);
        $job->handle($mockClient, $mockNormalizer, $mockFinder);

        // No new report should be auto-created. The seeded $report is the only one.
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseMissing('reports', ['accepted_from_submission_id' => $submission->id]);
    }
}
