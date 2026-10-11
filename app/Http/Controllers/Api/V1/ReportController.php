<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreFeedbackRequest;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Jobs\AnalyzeReportJob;
use App\Models\Category;
use App\Models\Report;
use App\Services\PriorityScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    private static int $publicCodeSeq = 0;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Report::with(['category', 'latestAiAnalysis', 'priorityScore'])
            ->orderByDesc('created_at');

        // Citizens see only their own reports.
        if ($user->isCitizen()) {
            $query->where('user_id', $user->id);
        }

        // Optional filters.
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $reports = $query->paginate((int) $request->query('per_page', 15));

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

    public function store(StoreReportRequest $request, PriorityScoreService $priority): JsonResponse
    {
        $user = $request->user();

        // Resolve category by ID or code.
        $categoryId = $request->category_id;
        if (!$categoryId && $request->category_code) {
            $category = Category::where('code', $request->category_code)->where('is_active', true)->first();
            $categoryId = $category?->id;
        }

        $report = Report::create([
            'public_code'     => $this->generatePublicCode(),
            'user_id'         => $user->id,
            'category_id'     => $categoryId,
            'title'           => $request->title,
            'description'     => $request->description,
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'address_text'    => $request->address_text,
            'occurred_at'     => $request->occurred_at,
            'status'          => 'submitted',
            'analysis_status' => 'queued',
        ]);

        // Store attachments with randomised filenames.
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $ext  = $file->getClientOriginalExtension() ?: 'jpg';
                $path = $file->storeAs(
                    'reports/' . $report->id,
                    Str::uuid() . '.' . $ext,
                    'private'
                );
                $report->attachments()->create([
                    'path'       => $path,
                    'kind'       => 'initial',
                    'mime_type'  => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                ]);
            }
        }

        // Initial priority score (no AI data yet).
        $priority->calculate($report->load('latestAiAnalysis', 'attachments'));

        // Dispatch async AI analysis.
        AnalyzeReportJob::dispatch($report->id);

        return response()->json([
            'data' => new ReportResource($report->load('category', 'attachments', 'latestAiAnalysis', 'priorityScore')),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $report = Report::with([
            'category',
            'attachments',
            'latestAiAnalysis',
            'priorityScore',
            'statusHistories',
        ])->findOrFail($id);

        if (!$request->user()->can('view', $report)) {
            abort(403);
        }

        return response()->json(['data' => new ReportResource($report)]);
    }

    public function storeFeedback(StoreFeedbackRequest $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);

        if (!$request->user()->can('feedback', $report)) {
            abort(403);
        }

        $feedback = $report->feedback()->create([
            'user_id' => $request->user()->id,
            'type'    => $request->type,
            'message' => $request->message,
        ]);

        return response()->json(['data' => ['id' => $feedback->id, 'type' => $feedback->type]], 201);
    }

    private function generatePublicCode(): string
    {
        $year  = date('Y');
        $count = Report::whereYear('created_at', $year)->count() + 1;

        return 'SOL-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
