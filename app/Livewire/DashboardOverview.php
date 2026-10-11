<?php

namespace App\Livewire;

use App\Models\Report;
use App\Models\ReportSubmission;
use App\Models\Category;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class DashboardOverview extends Component
{
    public function render()
    {
        // Submission stats (v2 pipeline)
        $submissionStats = ReportSubmission::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Report stats
        $reportStats = Report::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalReports      = Report::count();
        $pendingModeration = $submissionStats->get('queued_for_moderation', 0)
            + $submissionStats->get('moderating', 0)
            + $submissionStats->get('manual_review', 0);
        $duplicateReview   = $submissionStats->get('duplicate_review', 0);
        $inProgress        = $reportStats->get('in_progress', 0);
        $pendingVerif      = $reportStats->get('resolved_pending_verification', 0);

        // Reports by category (last 30 days)
        $byCategory = Report::select('categories.name', DB::raw('count(*) as total'))
            ->leftJoin('categories', 'reports.category_id', '=', 'categories.id')
            ->where('reports.created_at', '>=', now()->subDays(30))
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        // Reports by day (last 14 days)
        $byDay = Report::selectRaw('DATE(created_at) as day, count(*) as total')
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        // Recent reports
        $recentReports = Report::with(['category', 'user'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // High priority submissions awaiting action
        $urgentSubmissions = ReportSubmission::with('selectedCategory')
            ->whereIn('status', ['manual_review', 'duplicate_review'])
            ->orderByDesc('received_at')
            ->limit(5)
            ->get();

        return view('livewire.dashboard-overview', compact(
            'totalReports', 'pendingModeration', 'duplicateReview',
            'inProgress', 'pendingVerif', 'byCategory', 'byDay',
            'recentReports', 'urgentSubmissions', 'reportStats', 'submissionStats'
        ))->layout('components.dashboard-layout', ['title' => 'Ringkasan Dashboard']);
    }
}
