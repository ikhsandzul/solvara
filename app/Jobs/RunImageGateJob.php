<?php

namespace App\Jobs;

use App\Models\AiAnalysisRun;
use App\Models\ReportModerationResult;
use App\Models\ReportSubmission;
use App\Services\FinalizeReportSubmissionService;
use App\Services\LangflowImageGateClient;
use App\Services\LangflowResponseNormalizer;
use App\Services\PrivateReportMediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunImageGateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(private string $submissionId) {}

    public function handle(
        LangflowImageGateClient  $client,
        LangflowResponseNormalizer $normalizer,
        FinalizeReportSubmissionService $finalizer,
        PrivateReportMediaStorage $storage
    ): void {
        $submission = ReportSubmission::find($this->submissionId);

        if (!$submission) {
            Log::warning('RunImageGateJob: submission not found', ['id' => $this->submissionId]);
            return;
        }

        // Skip if already past this stage.
        if (!in_array($submission->status, ['received', 'queued_for_moderation'], true)) {
            return;
        }

        $submission->update(['status' => 'moderating']);

        $startMs = hrtime(true);

        // Create analysis run record.
        $run = AiAnalysisRun::create([
            'submission_id'  => $submission->id,
            'flow_type'      => 'image_gate',
            'flow_id_or_version' => config('langflow.image_gate_flow_id'),
            'status'         => 'processing',
            'attempt_number' => $this->attempts(),
        ]);

        if (!$client->isConfigured()) {
            // Langflow not configured: route to manual review.
            $run->update(['status' => 'failed', 'error_code' => 'FLOW_NOT_CONFIGURED']);
            $submission->update(['status' => 'manual_review']);
            Log::info('RunImageGateJob: Langflow Image Gate not configured; routed to manual_review', [
                'submission_id' => $submission->id,
            ]);
            return;
        }

        $payload = [
            'submission_id'     => $submission->id,
            'description'       => $submission->description,
            'selected_category' => $submission->selectedCategory?->code,
            'occurred_at'       => $submission->occurred_at?->toIso8601String(),
            // image_input: for MVP, we send a reference; real implementation uses file upload or signed URL adapter.
            'image_input'       => 'quarantine:' . $submission->temporary_attachment_path,
        ];

        try {
            $raw        = $client->run($payload);
            $durationMs = (int) ((hrtime(true) - $startMs) / 1_000_000);
            $normalized = $normalizer->normalizeImageGate($raw);

            if ($normalized === null) {
                throw new \RuntimeException('Flow A response could not be normalized to expected schema.');
            }

            $run->update([
                'status'                  => 'completed',
                'duration_ms'             => $durationMs,
                'sanitized_output_json'   => $normalized,
                'response_schema_version' => '1.0',
            ]);

            // Save moderation result.
            ReportModerationResult::create([
                'submission_id'      => $submission->id,
                'ai_analysis_run_id' => $run->id,
                'moderation_decision'=> $normalized['moderation_decision'],
                'is_civic_issue'     => $normalized['is_civic_issue'],
                'suggested_category' => $normalized['suggested_category'],
                'image_description'  => $normalized['image_description'],
                'description_match'  => $normalized['description_match'],
                'confidence_band'    => $normalized['confidence_band'],
                'reasons_json'       => $normalized['reasons'],
                'needs_human_review' => $normalized['needs_human_review'],
                'policy_version'     => 'v1',
            ]);

            $this->handleModerationDecision($submission, $normalized, $finalizer);
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startMs) / 1_000_000);
            Log::error('RunImageGateJob failed', [
                'submission_id' => $this->submissionId,
                'attempt'       => $this->attempts(),
                'error'         => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $run->update(['status' => 'failed', 'error_code' => 'MAX_RETRIES_EXCEEDED', 'duration_ms' => $durationMs]);
                $submission->update(['status' => 'processing_failed']);
            } else {
                $run->update(['status' => 'failed', 'error_code' => 'RETRY_PENDING', 'duration_ms' => $durationMs]);
                $submission->update(['status' => 'queued_for_moderation']);
                throw $e; // Let queue retry.
            }
        }
    }

    private function handleModerationDecision(
        ReportSubmission $submission,
        array $normalized,
        FinalizeReportSubmissionService $finalizer
    ): void {
        $decision = $normalized['moderation_decision'];

        if ($decision === 'approved' && !$normalized['needs_human_review']) {
            // Route to duplicate check.
            $submission->update(['status' => 'queued_for_duplicate_check']);
            RunDuplicateIntelligenceJob::dispatch($submission->id);
        } elseif ($decision === 'rejected' && !$normalized['needs_human_review']) {
            // Auto-reject only when confidence is high and no human review needed.
            $finalizer->reject($submission, 0, 'Ditolak moderasi gambar otomatis.');
        } else {
            // Ambiguous, manual_review needed, or uncertainty.
            $submission->update(['status' => 'manual_review']);
        }
    }
}
