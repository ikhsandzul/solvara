<?php

namespace Tests\Feature\Api\V2;

use App\Jobs\RunImageGateJob;
use App\Models\Category;
use App\Models\ReportSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->citizen = User::factory()->citizen()->create();
        $this->officer = User::factory()->officer()->create();

        Category::create(['code' => 'road_damage', 'name' => 'Jalan Rusak', 'is_active' => true]);
    }

    public function test_citizen_can_create_submission_returns_202(): void
    {
        Queue::fake();
        Storage::fake('private');

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->call('POST', '/api/v1/report-submissions', [
                'description' => 'Jalan berlubang sangat dalam di depan sekolah, berbahaya untuk pengguna jalan.',
                'occurred_at' => now()->toIso8601String(),
            ], [], ['attachments' => [$file]]);

        $response->assertStatus(202)
            ->assertJsonStructure(['data' => ['submission_id', 'status', 'message']])
            ->assertJsonPath('data.status', 'queued_for_moderation');

        Queue::assertPushed(RunImageGateJob::class);
    }

    public function test_submission_without_photo_routes_to_manual_review(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/v1/report-submissions', [
                'description' => 'Jalan berlubang sangat dalam di depan sekolah, berbahaya untuk pengguna jalan.',
                'occurred_at' => now()->toIso8601String(),
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'manual_review');

        Queue::assertNotPushed(RunImageGateJob::class);
    }

    public function test_description_too_short_rejected(): void
    {
        $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/v1/report-submissions', [
                'description' => 'Short.',
                'occurred_at' => now()->toIso8601String(),
            ])->assertStatus(422);
    }

    public function test_invalid_mime_rejected(): void
    {
        Storage::fake('private');

        // Create a fake file that claims to be image but we'll pass a text file.
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->call('POST', '/api/v1/report-submissions', [
                'description' => 'Jalan berlubang sangat dalam di depan sekolah, berbahaya untuk pengguna jalan.',
                'occurred_at' => now()->toIso8601String(),
            ], [], ['attachments' => [$file]]);

        // Laravel validation 'image' rule rejects non-image files.
        $response->assertStatus(422);
    }

    public function test_citizen_can_view_own_submission(): void
    {
        $submission = ReportSubmission::create([
            'user_id'     => $this->citizen->id,
            'description' => 'Test description',
            'occurred_at' => now(),
            'received_at' => now(),
            'status'      => 'queued_for_moderation',
            'expires_at'  => now()->addHours(24),
        ]);

        $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/v1/report-submissions/' . $submission->id)
            ->assertStatus(200)
            ->assertJsonPath('data.submission_id', $submission->id);
    }

    public function test_citizen_cannot_view_others_submission(): void
    {
        $other = User::factory()->citizen()->create();
        $submission = ReportSubmission::create([
            'user_id'     => $other->id,
            'description' => 'Test description',
            'occurred_at' => now(),
            'received_at' => now(),
            'status'      => 'queued_for_moderation',
            'expires_at'  => now()->addHours(24),
        ]);

        $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/v1/report-submissions/' . $submission->id)
            ->assertStatus(403);
    }

    public function test_idempotency_key_prevents_duplicate_submission(): void
    {
        Queue::fake();

        $payload = [
            'description'     => 'Jalan berlubang sangat dalam di depan sekolah, berbahaya untuk pengguna jalan.',
            'occurred_at'     => now()->toIso8601String(),
            'idempotency_key' => 'test-idem-key-1234',
        ];

        $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/v1/report-submissions', $payload)
            ->assertStatus(202);

        // Second request with same key returns existing submission.
        $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/v1/report-submissions', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'manual_review'); // no photo = manual_review
    }

    public function test_officer_can_view_any_submission(): void
    {
        $submission = ReportSubmission::create([
            'user_id'     => $this->citizen->id,
            'description' => 'Test',
            'occurred_at' => now(),
            'received_at' => now(),
            'status'      => 'queued_for_moderation',
            'expires_at'  => now()->addHours(24),
        ]);

        $this->actingAs($this->officer, 'sanctum')
            ->getJson('/api/v1/report-submissions/' . $submission->id)
            ->assertStatus(200);
    }

    public function test_unauthenticated_cannot_create_submission(): void
    {
        $this->postJson('/api/v1/report-submissions', [
            'description' => 'Jalan berlubang sangat dalam.',
            'occurred_at' => now()->toIso8601String(),
        ])->assertStatus(401);
    }
}
