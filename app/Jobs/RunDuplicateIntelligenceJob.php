<?php

namespace App\Jobs;

use App\Models\AiAnalysisRun;
use App\Models\ReportSimilarityMatch;
use App\Models\ReportSubmission;
use App\Services\FindNearbyReportCandidates;
use App\Services\LangflowDuplicateClient;
use App\Services\LangflowResponseNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDuplicateIntelligenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(private string $submissionId) {}

    public function handle(
        LangflowDuplicateClient    $client,
        LangflowResponseNormalizer $normalizer,
        FindNearbyReportCandidates $candidateFinder
    ): void {
        $submission = ReportSubmission::find($this->submissionId);

        if (!$submission || $submission->status !== 'queued_for_duplicate_check') {
            return;
        }

        $submission->update(['status' => 'checking_duplicates']);

        // --- Spatial candidate search ---
        $candidates = [];
        if ($submission->hasCoordinates()) {
            $candidates = $candidateFinder->find(
                (float) $submission->latitude,
                (float) $submission->longitude,
                (int) config('solvara.duplicate_radius_meters', 100),
                (int) config('solvara.duplicate_max_candidates', 10),
                (int) config('solvara.duplicate_active_lookback_days', 180),
                (int) config('solvara.duplicate_history_lookback_days', 365)
            );
        }

        // No candidates → skip Flow B, go straight to acceptance queue.
        if (empty($candidates)) {
            $submission->update(['status' => 'manual_review']); // officer finalizes
            Log::info('RunDuplicateIntelligenceJob: no nearby candidates; submission routed to manual_review', [
                'submission_id' => $this->submissionId,
            ]);
            return;
        }

        $candidateIds = array_map(fn ($c) => (string) $c->id, $candidates);

        // Retrieve image description from Flow A result for context.
        $imageDesc = $submission->moderationResult?->image_description;

        $startMs = hrtime(true);

        $run = AiAnalysisRun::create([
            'submission_id'  => $submission->id,
            'flow_type'      => 'duplicate_intelligence',
            'flow_id_or_version' => config('langflow.duplicate_flow_id'),
            'status'         => 'processing',
            'attempt_number' => $this->attempts(),
        ]);

        if (!$client->isConfigured()) {
            $run->update(['status' => 'failed', 'error_code' => 'FLOW_NOT_CONFIGURED']);
            // Store candidates without AI assessment; route to human review.
            $this->storeCandidates($submission, $candidates, []);
            $submission->update(['status' => 'duplicate_review']);
            return;
        }

        $payload = [
            'submission' => [
                'description'      => $submission->description,
                'category'         => $submission->selectedCategory?->code,
                'latitude'         => $submission->latitude,
                'longitude'        => $submission->longitude,
                'image_description'=> $imageDesc,
            ],
            'candidates' => array_map(fn ($c) => [
                'report_id'         => (string) $c->id,
                'distance_m'        => round($c->distance_m, 1),
                'status'            => $c->status,
                'category'          => $c->category_id,
                'description'       => mb_substr($c->description, 0, 300),
                'image_description' => null, // populated when available from moderation data
            ], $candidates),
        ];

        try {
            $raw        = $client->run($payload);
            $durationMs = (int) ((hrtime(true) - $startMs) / 1_000_000);
            $normalized = $normalizer->normalizeDuplicate($raw, $candidateIds);

            if ($normalized === null) {
                throw new \RuntimeException('Flow B response could not be normalized.');
            }

            $run->update([
                'status'                  => 'completed',
                'duration_ms'             => $durationMs,
                'sanitized_output_json'   => $normalized,
                'response_schema_version' => '1.0',
            ]);

            $this->storeCandidates($submission, $candidates, $normalized['matches']);

            $decision = $normalized['duplicate_decision'];

            if ($decision === 'no_match' && !$normalized['needs_human_review']) {
                // No duplicates found — route for officer finalization.
                $submission->update(['status' => 'manual_review']);
            } else {
                // Possible duplicate or uncertain — always require review.
                $submission->update(['status' => 'duplicate_review']);
            }
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startMs) / 1_000_000);
            Log::error('RunDuplicateIntelligenceJob failed', [
                'submission_id' => $this->submissionId,
                'attempt'       => $this->attempts(),
                'error'         => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $run->update(['status' => 'failed', 'error_code' => 'MAX_RETRIES_EXCEEDED', 'duration_ms' => $durationMs]);
                $submission->update(['status' => 'processing_failed']);
            } else {
                $run->update(['status' => 'failed', 'error_code' => 'RETRY_PENDING', 'duration_ms' => $durationMs]);
                $submission->update(['status' => 'queued_for_duplicate_check']);
                throw $e;
            }
        }
    }

    private function storeCandidates(ReportSubmission $submission, array $candidates, array $matches): void
    {
        // Index matches by candidate_report_id for quick lookup.
        $matchIndex = [];
        foreach ($matches as $m) {
            $matchIndex[(string) $m['candidate_report_id']] = $m;
        }

        foreach ($candidates as $c) {
            $match = $matchIndex[(string) $c->id] ?? null;

            ReportSimilarityMatch::create([
                'submission_id'       => $submission->id,
                'candidate_report_id' => $c->id,
                'distance_m'          => round($c->distance_m, 2),
                'match_type'          => $match['match_type'] ?? null,
                'confidence_band'     => $match['confidence_band'] ?? null,
                'reason'              => $match['reason'] ?? null,
                'review_status'       => 'pending',
            ]);
        }
    }
}
