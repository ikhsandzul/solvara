<?php

namespace App\Http\Controllers\Api\V1\Officer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\OfficerReviewRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\ReportSubmission;
use App\Services\FinalizeReportSubmissionService;
use Illuminate\Http\JsonResponse;

class OfficerSubmissionController extends Controller
{
    public function __construct(private FinalizeReportSubmissionService $finalizer) {}

    public function review(OfficerReviewRequest $request, string $id): JsonResponse
    {
        $submission = ReportSubmission::with(['selectedCategory', 'similarityMatches', 'moderationResult'])
            ->find($id);

        if (!$submission) {
            abort(404);
        }

        $decision = $request->decision;
        $note     = $request->note;
        $actorId  = $request->user()->id;

        switch ($decision) {
            case 'approve_new':
                $report = $this->finalizer->accept($submission, $actorId, $note);
                return response()->json([
                    'data' => [
                        'submission_id' => $id,
                        'status'        => 'accepted',
                        'report_id'     => $report->id,
                        'public_code'   => $report->public_code,
                    ],
                ]);

            case 'confirm_duplicate':
                $dupId = $request->duplicate_report_id;
                if (!$dupId) {
                    return response()->json([
                        'error' => ['code' => 'MISSING_FIELD', 'message' => 'duplicate_report_id wajib diisi.'],
                    ], 422);
                }
                $this->finalizer->linkToExisting($submission, $dupId);
                return response()->json(['data' => ['submission_id' => $id, 'status' => 'linked_to_existing_report']]);

            case 'reject':
                $this->finalizer->reject($submission, $actorId, $note ?? 'Ditolak petugas.');
                return response()->json(['data' => ['submission_id' => $id, 'status' => 'rejected']]);

            default:
                return response()->json(['error' => ['code' => 'INVALID_DECISION']], 422);
        }
    }
}
