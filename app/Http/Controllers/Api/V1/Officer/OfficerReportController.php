<?php

namespace App\Http\Controllers\Api\V1\Officer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Officer\UpdateStatusRequest;
use App\Http\Requests\Officer\VerificationRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Services\ReportStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficerReportController extends Controller
{
    public function __construct(private ReportStatusService $statusService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $byStatus   = Report::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byCategory = Report::selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->with('category:id,name,code')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->category?->code ?? 'unknown' => $r->total]);

        return response()->json([
            'data' => [
                'by_status'   => $byStatus,
                'by_category' => $byCategory,
                'total'       => Report::count(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Report::with(['category', 'user:id,name', 'latestAiAnalysis', 'priorityScore'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('public_code', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => ReportResource::collection($reports->items()),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
                'per_page'     => $reports->perPage(),
                'total'        => $reports->total(),
            ],
        ]);
    }

    public function updateStatus(UpdateStatusRequest $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        if (!$request->user()->can('updateStatus', $report)) {
            abort(403);
        }

        try {
            $this->statusService->transition(
                $report,
                $request->status,
                $request->user()->id,
                $request->note
            );
        } catch (\DomainException $e) {
            return response()->json([
                'error' => ['code' => 'INVALID_TRANSITION', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json(['data' => new ReportResource($report->fresh()->load('statusHistories'))]);
    }

    public function verification(VerificationRequest $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        if (!$request->user()->can('verify', $report)) {
            abort(403);
        }

        // Map decision to status transition.
        $newStatus = $request->decision === 'verified' ? 'verified' : 'reopened';

        // The report must be in resolved_pending_verification (or verified for reopen).
        $allowedSources = $request->decision === 'verified'
            ? ['resolved_pending_verification']
            : ['verified', 'resolved_pending_verification'];

        if (!in_array($report->status, $allowedSources)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_TRANSITION',
                    'message' => "Laporan dengan status '{$report->status}' tidak dapat diverifikasi/dibuka kembali dari sini.",
                ],
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($report, $request, $newStatus) {
            $oldStatus = $report->status;
            $report->status = $newStatus;
            $report->save();

            $report->statusHistories()->create([
                'actor_id'   => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => $request->note,
                'created_at' => now(),
            ]);

            $report->verificationReviews()->create([
                'reviewer_id' => $request->user()->id,
                'decision'    => $request->decision,
                'note'        => $request->note,
                'created_at'  => now(),
            ]);
        });

        return response()->json(['data' => new ReportResource($report->fresh())]);
    }
}
