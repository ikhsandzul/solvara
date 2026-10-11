<?php

namespace App\Services;

use App\Models\AiAnalysis;
use App\Models\Report;

class PriorityScoreService
{
    public const RULES_VERSION = '1.0';

    /**
     * Calculate and persist/update the priority score for a report.
     */
    public function calculate(Report $report): void
    {
        $factors = [];
        $score = 0;

        // Factor 1: AI urgency suggestion (max 40 points)
        $analysis = $report->latestAiAnalysis;
        $urgencyMap = ['critical' => 40, 'high' => 30, 'medium' => 20, 'low' => 10];

        if ($analysis && $analysis->status === 'completed' && isset($urgencyMap[$analysis->urgency_suggestion])) {
            $urgencyPoints = $urgencyMap[$analysis->urgency_suggestion];
            $factors['ai_urgency'] = [
                'value' => $analysis->urgency_suggestion,
                'points' => $urgencyPoints,
                'note' => 'AI urgency suggestion (unverified)',
            ];
            $score += $urgencyPoints;
        } else {
            $factors['ai_urgency'] = ['value' => null, 'points' => 0, 'note' => 'insufficient_data'];
        }

        // Factor 2: Age of report (max 20 points; older = more urgent)
        $ageDays = (int) $report->created_at->diffInDays(now());
        $agePoints = min(20, intdiv($ageDays, 3) * 4); // +4 per 3 days, capped at 20
        $factors['age_days'] = ['value' => $ageDays, 'points' => $agePoints];
        $score += $agePoints;

        // Factor 3: Number of related reports from AI (max 20 points)
        $relatedCount = $analysis ? count($analysis->related_report_ids_json ?? []) : 0;
        $relatedPoints = min(20, $relatedCount * 5);
        $factors['related_reports'] = ['value' => $relatedCount, 'points' => $relatedPoints];
        $score += $relatedPoints;

        // Factor 4: Has location data (10 points bonus)
        $locationPoints = ($report->latitude !== null && $report->longitude !== null) ? 10 : 0;
        $factors['has_location'] = ['value' => $locationPoints > 0, 'points' => $locationPoints];
        $score += $locationPoints;

        // Factor 5: Has attachments (10 points bonus)
        $hasAttachments = $report->attachments()->exists();
        $attachmentPoints = $hasAttachments ? 10 : 0;
        $factors['has_attachments'] = ['value' => $hasAttachments, 'points' => $attachmentPoints];
        $score += $attachmentPoints;

        $score = min(100, $score);
        $band = match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default      => 'low',
        };

        \App\Models\PriorityScore::updateOrCreate(
            ['report_id' => $report->id],
            [
                'score'                => $score,
                'band'                 => $band,
                'factor_breakdown_json'=> $factors,
                'rules_version'        => self::RULES_VERSION,
            ]
        );
    }
}
