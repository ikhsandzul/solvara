<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\Report;
use App\Services\LangflowClient;
use App\Services\PriorityScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum attempts (initial + 2 retries). */
    public int $tries = 3;

    /** Backoff in seconds between retries. */
    public array $backoff = [30, 120];

    public function __construct(private int $reportId) {}

    public function handle(LangflowClient $langflow, PriorityScoreService $priority): void
    {
        $report = Report::with(['attachments', 'category'])->find($this->reportId);

        if (!$report) {
            Log::warning('AnalyzeReportJob: report not found', ['report_id' => $this->reportId]);
            return;
        }

        // Ensure idempotency: use any existing analysis record or create one.
        /** @var AiAnalysis $analysis */
        $analysis = AiAnalysis::firstOrCreate(
            ['report_id' => $report->id],
            ['status' => 'queued']
        );

        // If already completed, skip.
        if ($analysis->status === 'completed') {
            return;
        }

        $analysis->update(['status' => 'processing']);
        $report->update(['analysis_status' => 'processing']);

        // Build sanitised payload (no PII, no tokens, no binary).
        $payload = $this->buildPayload($report);

        try {
            $raw = $langflow->run($payload);
            $normalized = $langflow->normalize($raw);

            if ($normalized === null) {
                throw new \RuntimeException('Langflow response could not be normalized to expected schema.');
            }

            $analysis->update($normalized + ['status' => 'completed', 'error_code' => null]);
            $report->update(['analysis_status' => 'completed']);

            // Recalculate priority with fresh AI data.
            $priority->calculate($report->fresh()->load('latestAiAnalysis', 'attachments'));
        } catch (\Throwable $e) {
            Log::error('AnalyzeReportJob failed', [
                'report_id' => $this->reportId,
                'attempt'   => $this->attempts(),
                'error'     => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $analysis->update(['status' => 'failed', 'error_code' => 'MAX_RETRIES_EXCEEDED']);
                $report->update(['analysis_status' => 'failed']);
            } else {
                $analysis->update(['status' => 'queued', 'error_code' => 'RETRY_PENDING']);
                throw $e; // Let the queue retry.
            }
        }
    }

    private function buildPayload(Report $report): array
    {
        // Find related candidate reports (same category, last 30 days, limit 10).
        $candidates = Report::where('id', '!=', $report->id)
            ->where('category_id', $report->category_id)
            ->where('created_at', '>=', now()->subDays(30))
            ->limit(10)
            ->get(['id', 'public_code', 'title', 'description', 'status', 'occurred_at']);

        return [
            'report_id'       => $report->public_code,
            'category'        => $report->category?->code,
            'description'     => $report->description,
            'title'           => $report->title,
            'occurred_at'     => $report->occurred_at?->toIso8601String(),
            'location_label'  => $report->address_text,
            'related_reports' => $candidates->map(fn ($r) => [
                'id'          => $r->public_code,
                'title'       => $r->title,
                'description' => mb_substr($r->description, 0, 200),
                'status'      => $r->status,
                'occurred_at' => $r->occurred_at?->toIso8601String(),
            ])->toArray(),
        ];
    }
}
