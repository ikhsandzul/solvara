<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerTest extends TestCase
{
    use RefreshDatabase;

    private User $citizen;
    private User $officer;
    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->citizen()->create();
        $this->officer = User::factory()->officer()->create();
        $this->report  = Report::factory()->create(['user_id' => $this->citizen->id]);
    }

    public function test_citizen_cannot_access_officer_dashboard(): void
    {
        $this->actingAs($this->citizen, 'sanctum')
            ->getJson('/api/v1/officer/dashboard/summary')
            ->assertStatus(403);
    }

    public function test_officer_can_access_dashboard(): void
    {
        $this->actingAs($this->officer, 'sanctum')
            ->getJson('/api/v1/officer/dashboard/summary')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['by_status', 'by_category', 'total']]);
    }

    public function test_officer_can_list_reports(): void
    {
        $this->actingAs($this->officer, 'sanctum')
            ->getJson('/api/v1/officer/reports')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_officer_can_change_status(): void
    {
        $this->actingAs($this->officer, 'sanctum')
            ->patchJson("/api/v1/officer/reports/{$this->report->id}/status", [
                'status' => 'needs_review',
                'note'   => 'Perlu ditinjau lebih lanjut.',
            ])->assertStatus(200)
              ->assertJsonPath('data.status', 'needs_review');

        $this->assertDatabaseHas('report_status_histories', [
            'report_id'  => $this->report->id,
            'old_status' => 'submitted',
            'new_status' => 'needs_review',
        ]);
    }

    public function test_illegal_status_transition_rejected(): void
    {
        $this->actingAs($this->officer, 'sanctum')
            ->patchJson("/api/v1/officer/reports/{$this->report->id}/status", [
                'status' => 'verified', // cannot jump from submitted to verified
            ])->assertStatus(422)
              ->assertJsonPath('error.code', 'INVALID_TRANSITION');
    }

    public function test_citizen_cannot_change_status(): void
    {
        $this->actingAs($this->citizen, 'sanctum')
            ->patchJson("/api/v1/officer/reports/{$this->report->id}/status", [
                'status' => 'needs_review',
            ])->assertStatus(403);
    }

    public function test_officer_can_verify_report(): void
    {
        // Move to resolved_pending_verification first.
        $service = app(ReportStatusService::class);
        $service->transition($this->report, 'needs_review', $this->officer->id);
        $service->transition($this->report, 'accepted', $this->officer->id);
        $service->transition($this->report, 'in_progress', $this->officer->id);
        $service->transition($this->report, 'resolved_pending_verification', $this->officer->id);

        $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/officer/reports/{$this->report->id}/verification", [
                'decision' => 'verified',
                'note'     => 'Bukti perbaikan sudah memadai.',
            ])->assertStatus(200)
              ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('verification_reviews', [
            'report_id' => $this->report->id,
            'decision'  => 'verified',
        ]);
    }

    public function test_verification_requires_correct_status(): void
    {
        // report is in 'submitted' — cannot verify yet.
        $this->actingAs($this->officer, 'sanctum')
            ->postJson("/api/v1/officer/reports/{$this->report->id}/verification", [
                'decision' => 'verified',
            ])->assertStatus(422);
    }

    public function test_officer_reports_list_supports_pagination(): void
    {
        Report::factory(25)->create(['user_id' => $this->citizen->id]);

        $this->actingAs($this->officer, 'sanctum')
            ->getJson('/api/v1/officer/reports?per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10);
    }
}
