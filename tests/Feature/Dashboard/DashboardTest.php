<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusHistory;
use App\Models\ReportSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;
    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->officer = User::factory()->officer()->create();
        $this->citizen = User::factory()->create(['role' => 'citizen']);
        Category::create(['code' => 'JAL', 'name' => 'Jalan Rusak', 'is_active' => true]);
    }

    // ── Auth ───────────────────────────────────────────────────────────

    public function test_login_page_renders(): void
    {
        $this->get('/dashboard/login')->assertStatus(200)->assertSee('Solvara');
    }

    public function test_officer_can_login(): void
    {
        $this->post('/dashboard/login', [
            'email'    => $this->officer->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard.home'));
    }

    public function test_citizen_cannot_login_to_dashboard(): void
    {
        $this->post('/dashboard/login', [
            'email'    => $this->citizen->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard/login')
          ->assertSessionHasErrors('email');
    }

    public function test_wrong_password_rejected(): void
    {
        $this->post('/dashboard/login', [
            'email'    => $this->officer->email,
            'password' => 'wrong',
        ])->assertRedirect('/dashboard/login')
          ->assertSessionHasErrors('email');
    }

    public function test_unauthenticated_redirect_to_login(): void
    {
        $this->get('/dashboard/home')->assertRedirect('/dashboard/login');
    }

    public function test_logout_redirects_to_login(): void
    {
        $this->actingAs($this->officer)
             ->post('/dashboard/logout')
             ->assertRedirect(route('dashboard.login'));
    }

    // ── Dashboard pages accessible ────────────────────────────────────

    public function test_home_page_accessible(): void
    {
        $this->actingAs($this->officer)
             ->get('/dashboard/home')
             ->assertStatus(200);
    }

    public function test_reports_page_accessible(): void
    {
        $this->actingAs($this->officer)
             ->get('/dashboard/reports')
             ->assertStatus(200);
    }

    public function test_moderation_page_accessible(): void
    {
        $this->actingAs($this->officer)
             ->get('/dashboard/moderation')
             ->assertStatus(200);
    }

    public function test_duplicates_page_accessible(): void
    {
        $this->actingAs($this->officer)
             ->get('/dashboard/duplicates')
             ->assertStatus(200);
    }

    public function test_verification_page_accessible(): void
    {
        $this->actingAs($this->officer)
             ->get('/dashboard/verification')
             ->assertStatus(200);
    }

    // ── Report detail ─────────────────────────────────────────────────

    public function test_report_detail_page_accessible(): void
    {
        $category = Category::first();
        $report   = Report::factory()->create([
            'user_id'     => $this->citizen->id,
            'category_id' => $category->id,
            'status'      => 'submitted',
        ]);

        $this->actingAs($this->officer)
             ->get("/dashboard/reports/{$report->id}")
             ->assertStatus(200)
             ->assertSee($report->public_code);
    }

    public function test_report_detail_404_for_missing(): void
    {
        $this->actingAs($this->officer)
             ->get('/dashboard/reports/999999')
             ->assertStatus(404);
    }

    // ── Attachment download ───────────────────────────────────────────

    public function test_attachment_download_returns_file(): void
    {
        Storage::fake('private');

        $category = Category::first();
        $report   = Report::factory()->create([
            'user_id'     => $this->citizen->id,
            'category_id' => $category->id,
        ]);

        Storage::disk('private')->put('reports/test/photo.jpg', 'fake-image-data');

        $att = ReportAttachment::create([
            'report_id'  => $report->id,
            'path'       => 'reports/test/photo.jpg',
            'kind'       => 'before_repair',
            'mime_type'  => 'image/jpeg',
            'size_bytes' => 100,
        ]);

        $this->actingAs($this->officer)
             ->get("/dashboard/attachments/{$att->id}")
             ->assertStatus(200);
    }

    public function test_attachment_404_for_missing_file(): void
    {
        Storage::fake('private');

        $category = Category::first();
        $report   = Report::factory()->create([
            'user_id'     => $this->citizen->id,
            'category_id' => $category->id,
        ]);

        $att = ReportAttachment::create([
            'report_id'  => $report->id,
            'path'       => 'reports/nonexistent/photo.jpg',
            'kind'       => 'before_repair',
            'mime_type'  => 'image/jpeg',
            'size_bytes' => 100,
        ]);

        $this->actingAs($this->officer)
             ->get("/dashboard/attachments/{$att->id}")
             ->assertStatus(404);
    }

    // ── Citizen cannot access dashboard ──────────────────────────────

    public function test_citizen_cannot_access_officer_pages(): void
    {
        $this->actingAs($this->citizen)
             ->get('/dashboard/home')
             ->assertStatus(403);
    }
}
