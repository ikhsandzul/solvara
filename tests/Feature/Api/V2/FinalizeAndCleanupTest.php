<?php

namespace Tests\Feature\Api\V2;

use App\Jobs\ExpireTemporarySubmissionsJob;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportSubmission;
use App\Models\User;
use App\Services\FinalizeReportSubmissionService;
use App\Services\PrivateReportMediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalizeAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $officer;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citizen  = User::factory()->citizen()->create();
        $this->officer  = User::factory()->officer()->create();
        $this->category = Category::create(['code' => 'road_damage', 'name' => 'Jalan Rusak', 'is_active' => true]);
    }

    public function test_accept_creates_official_report_and_moves_photo(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('submissions/quarantine/test.jpg', 'fakecontent');

        $submission = ReportSubmission::create([
            'user_id'                   => $this->citizen->id,
            'description'               => 'Jalan berlubang dalam.',
            'selected_category_id'      => $this->category->id,
            'occurred_at'               => now(),
            'received_at'               => now(),
            'status'                    => 'manual_review',
            'temporary_attachment_path' => 'submissions/quarantine/test.jpg',
            'detected_mime'             => 'image/jpeg',
            'size_bytes'                => 11,
            'expires_at'                => now()->addHours(24),
        ]);

        $finalizer = app(FinalizeReportSubmissionService::class);
        $report    = $finalizer->accept($submission, $this->officer->id, 'Officer approved.');

        // Report created.
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'submitted']);

        // Attachment record created.
        $this->assertDatabaseHas('report_attachments', ['report_id' => $report->id]);

        // Submission marked accepted and linked.
        $submission->refresh();
        $this->assertEquals('accepted', $submission->status);
        $this->assertEquals((string) $report->id, (string) $submission->accepted_report_id);

        // Temp file removed from quarantine.
        $this->assertFalse(Storage::disk('private')->exists('submissions/quarantine/test.jpg'));
    }

    public function test_reject_removes_temporary_photo(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('submissions/quarantine/reject.jpg', 'fakecontent');

        $submission = ReportSubmission::create([
            'user_id'                   => $this->citizen->id,
            'description'               => 'Test',
            'occurred_at'               => now(),
            'received_at'               => now(),
            'status'                    => 'manual_review',
            'temporary_attachment_path' => 'submissions/quarantine/reject.jpg',
            'expires_at'                => now()->addHours(24),
        ]);

        $finalizer = app(FinalizeReportSubmissionService::class);
        $finalizer->reject($submission, $this->officer->id, 'Gambar tidak relevan.');

        $submission->refresh();
        $this->assertEquals('rejected', $submission->status);
        $this->assertNull($submission->temporary_attachment_path);
        $this->assertFalse(Storage::disk('private')->exists('submissions/quarantine/reject.jpg'));

        // No report created.
        $this->assertDatabaseEmpty('reports');
    }

    public function test_expire_job_cleans_expired_submissions(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('submissions/quarantine/old.jpg', 'fakecontent');

        $submission = ReportSubmission::create([
            'user_id'                   => $this->citizen->id,
            'description'               => 'Test expired',
            'occurred_at'               => now(),
            'received_at'               => now()->subHours(25),
            'status'                    => 'queued_for_moderation',
            'temporary_attachment_path' => 'submissions/quarantine/old.jpg',
            'expires_at'                => now()->subHour(), // already expired
        ]);

        $job = new ExpireTemporarySubmissionsJob();
        $job->handle(app(PrivateReportMediaStorage::class));

        $submission->refresh();
        $this->assertEquals('expired', $submission->status);
        $this->assertNull($submission->temporary_attachment_path);
        $this->assertFalse(Storage::disk('private')->exists('submissions/quarantine/old.jpg'));
    }

    public function test_idempotent_accept_does_not_create_duplicate_report(): void
    {
        Storage::fake('private');

        $submission = ReportSubmission::create([
            'user_id'              => $this->citizen->id,
            'description'          => 'Test dedup',
            'selected_category_id' => $this->category->id,
            'occurred_at'          => now(),
            'received_at'          => now(),
            'status'               => 'manual_review',
            'expires_at'           => now()->addHours(24),
        ]);

        $finalizer = app(FinalizeReportSubmissionService::class);
        $report1   = $finalizer->accept($submission, $this->officer->id);

        // The unique constraint on accepted_from_submission_id prevents a second creation.
        $this->expectException(\Throwable::class);
        $submission->update(['status' => 'manual_review']); // reset for second attempt
        $finalizer->accept($submission, $this->officer->id);
    }

    public function test_officer_review_endpoint_approve_creates_report(): void
    {
        Storage::fake('private');

        $submission = ReportSubmission::create([
            'user_id'              => $this->citizen->id,
            'description'          => 'Jalan berlubang dan sangat berbahaya di sini.',
            'selected_category_id' => $this->category->id,
            'occurred_at'          => now(),
            'received_at'          => now(),
            'status'               => 'manual_review',
            'expires_at'           => now()->addHours(24),
        ]);

        $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/officer/report-submissions/{$submission->id}/review", [
                'decision' => 'approve_new',
                'note'     => 'Terverifikasi.',
            ])->assertStatus(200)
              ->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('reports', ['accepted_from_submission_id' => $submission->id]);
    }

    public function test_officer_review_endpoint_reject(): void
    {
        Storage::fake('private');

        $submission = ReportSubmission::create([
            'user_id'     => $this->citizen->id,
            'description' => 'Test',
            'occurred_at' => now(),
            'received_at' => now(),
            'status'      => 'manual_review',
            'expires_at'  => now()->addHours(24),
        ]);

        $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/officer/report-submissions/{$submission->id}/review", [
                'decision' => 'reject',
                'note'     => 'Tidak relevan.',
            ])->assertStatus(200)
              ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseEmpty('reports');
    }

    public function test_citizen_cannot_access_officer_review_endpoint(): void
    {
        $submission = ReportSubmission::create([
            'user_id'     => $this->citizen->id,
            'description' => 'Test',
            'occurred_at' => now(),
            'received_at' => now(),
            'status'      => 'manual_review',
            'expires_at'  => now()->addHours(24),
        ]);

        $this->actingAs($this->citizen, 'sanctum')
            ->postJson("/api/v1/officer/report-submissions/{$submission->id}/review", [
                'decision' => 'approve_new',
            ])->assertStatus(403);
    }
}
