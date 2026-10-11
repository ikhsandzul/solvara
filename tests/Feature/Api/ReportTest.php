<?php

namespace Tests\Feature\Api;

use App\Jobs\AnalyzeReportJob;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_citizen_can_create_report(): void
    {
        Queue::fake();
        Storage::fake('private');

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'description' => 'Jalan berlubang di depan sekolah dasar sangat berbahaya.',
                'title'       => 'Jalan Berlubang',
                'category_id' => $this->category->id,
                'occurred_at' => now()->toIso8601String(),
                'address_text'=> 'Jl. Merdeka No. 1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.analysis_status', 'queued');

        Queue::assertPushed(AnalyzeReportJob::class);
    }

    public function test_report_description_too_short_fails(): void
    {
        $this->actingAs($this->citizen, 'sanctum')
            ->postJson('/api/v1/reports', [
                'description' => 'Short.',
                'occurred_at' => now()->toIso8601String(),
            ])->assertStatus(422);
    }

    public function test_citizen_cannot_see_other_citizen_report(): void
    {
        $other = User::factory()->citizen()->create();
        $report = Report::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/v1/reports/' . $report->id)
            ->assertStatus(403);
    }

    public function test_citizen_cannot_see_other_report_in_list(): void
    {
        $other  = User::factory()->citizen()->create();
        $report = Report::factory()->create(['user_id' => $other->id]);
        Report::factory()->create(['user_id' => $this->citizen->id]);

        $result = $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/v1/reports')
            ->assertStatus(200)
            ->json('data');

        $ids = array_column($result, 'id');
        $this->assertNotContains($report->id, $ids);
    }

    public function test_officer_can_see_any_report(): void
    {
        $report = Report::factory()->create(['user_id' => $this->citizen->id]);

        $this->actingAs($this->officer, 'sanctum')
            ->getJson('/api/v1/reports/' . $report->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $report->id);
    }

    public function test_citizen_can_submit_feedback_on_own_report(): void
    {
        $report = Report::factory()->create(['user_id' => $this->citizen->id]);

        $this->actingAs($this->citizen, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/feedback", [
                'type'    => 'satisfied',
                'message' => 'Terima kasih, laporan saya diproses dengan cepat.',
            ])->assertStatus(201);
    }

    public function test_citizen_cannot_give_feedback_on_other_report(): void
    {
        $other  = User::factory()->citizen()->create();
        $report = Report::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->citizen, 'sanctum')
            ->postJson("/api/v1/reports/{$report->id}/feedback", [
                'type' => 'satisfied',
            ])->assertStatus(403);
    }

    public function test_file_upload_stores_attachment(): void
    {
        Queue::fake();
        Storage::fake('private');

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($this->citizen, 'sanctum')
            ->call('POST', '/api/v1/reports', [
                'description' => 'Jalan berlubang sangat dalam dan berbahaya bagi pengguna jalan.',
                'occurred_at' => now()->toIso8601String(),
            ], [], ['attachments' => [$file]]);

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('data.attachments'));
    }

    public function test_unauthenticated_cannot_create_report(): void
    {
        $this->postJson('/api/v1/reports', [
            'description' => 'Test deskripsi yang cukup panjang untuk lolos validasi minimal.',
            'occurred_at' => now()->toIso8601String(),
        ])->assertStatus(401);
    }
}
