<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\DuplicateResponseRequest;
use App\Http\Requests\Submission\StoreSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Jobs\RunImageGateJob;
use App\Models\Category;
use App\Models\ReportSubmission;
use App\Services\FinalizeReportSubmissionService;
use App\Services\ImageValidationService;
use App\Services\PrivateReportMediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReportSubmissionController extends Controller
{
    public function __construct(
        private ImageValidationService $imageValidator,
        private PrivateReportMediaStorage $mediaStorage,
        private FinalizeReportSubmissionService $finalizer
    ) {}

    public function store(StoreSubmissionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Idempotency: if same key already has a non-failed submission, return it.
        if ($ikey = $request->idempotency_key) {
            $existing = ReportSubmission::where('idempotency_key', $ikey)
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['processing_failed', 'expired', 'rejected'])
                ->first();
            if ($existing) {
                return response()->json(['data' => [
                    'submission_id' => $existing->id,
                    'status'        => $existing->status,
                    'message'       => 'Laporan dengan kunci idempotency ini sudah ada.',
                ]], 200);
            }
        }

        // Resolve category.
        $categoryId = $request->selected_category_id;
        if (!$categoryId && $request->category_code) {
            $category   = Category::where('code', $request->category_code)->where('is_active', true)->first();
            $categoryId = $category?->id;
        }

        // Validate and store first attachment (MVP: 1 primary photo).
        $tempPath    = null;
        $detectedMime= null;
        $sizeBytes   = null;
        $sha256      = null;

        if ($request->hasFile('attachments')) {
            $file = $request->file('attachments')[0];

            if ($err = $this->imageValidator->validate($file)) {
                return response()->json([
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => $err],
                ], 422);
            }

            $tempPath     = $this->mediaStorage->storeTemporary($file);
            $detectedMime = $this->imageValidator->detectedMime($file);
            $sizeBytes    = $file->getSize();
            $sha256       = hash_file('sha256', $file->getRealPath());
        }

        $ttlHours = (int) config('solvara.report_submission_temp_ttl_hours', 24);

        $submission = ReportSubmission::create([
            'user_id'                    => $user->id,
            'description'                => $request->description,
            'selected_category_id'       => $categoryId,
            'latitude'                   => $request->latitude,
            'longitude'                  => $request->longitude,
            'address_text'               => $request->address_text,
            'occurred_at'                => $request->occurred_at,
            'received_at'                => now(),
            'status'                     => $tempPath ? 'queued_for_moderation' : 'manual_review',
            'temporary_attachment_path'  => $tempPath,
            'detected_mime'              => $detectedMime,
            'size_bytes'                 => $sizeBytes,
            'sha256'                     => $sha256,
            'gps_accuracy_m'             => $request->gps_accuracy_m,
            'idempotency_key'            => $request->idempotency_key,
            'expires_at'                 => now()->addHours($ttlHours),
        ]);

        // Dispatch async moderation job.
        if ($tempPath) {
            RunImageGateJob::dispatch($submission->id);
        }

        return response()->json([
            'data' => [
                'submission_id' => $submission->id,
                'status'        => $submission->status,
                'message'       => 'Laporan sedang memeriksa foto dan informasi yang dikirim.',
            ],
        ], 202);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $submission = ReportSubmission::with(['selectedCategory', 'moderationResult', 'similarityMatches'])
            ->find($id);

        if (!$submission) {
            abort(404);
        }

        // Citizens see only own submissions; officers see all.
        if (!$request->user()->isOfficer() && $submission->user_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json(['data' => new SubmissionResource($submission)]);
    }

    /**
     * Citizen response to a duplicate suggestion.
     */
    public function duplicateResponse(DuplicateResponseRequest $request, string $id): JsonResponse
    {
        $submission = ReportSubmission::find($id);

        if (!$submission || $submission->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($submission->status !== 'duplicate_review') {
            return response()->json([
                'error' => ['code' => 'INVALID_STATE', 'message' => 'Submission tidak dalam status duplicate_review.'],
            ], 422);
        }

        if ($request->response === 'same_issue') {
            $candidateId = $request->candidate_report_id;
            // Validate that candidate_report_id is in the actual similarity matches.
            $valid = $submission->similarityMatches()->where('candidate_report_id', $candidateId)->exists();
            if (!$valid) {
                return response()->json([
                    'error' => ['code' => 'INVALID_CANDIDATE', 'message' => 'Kandidat tidak dikenal.'],
                ], 422);
            }
            $this->finalizer->linkToExisting($submission, $candidateId);
        } else {
            // Citizen says it's different — route to officer review.
            $submission->update(['status' => 'manual_review']);
        }

        return response()->json(['data' => ['submission_id' => $id, 'status' => $submission->fresh()->status]]);
    }
}
