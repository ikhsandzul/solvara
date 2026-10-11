<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportModerationResult extends Model
{
    protected $fillable = [
        'submission_id',
        'ai_analysis_run_id',
        'moderation_decision',
        'is_civic_issue',
        'suggested_category',
        'image_description',
        'description_match',
        'confidence_band',
        'reasons_json',
        'policy_version',
        'needs_human_review',
    ];

    protected function casts(): array
    {
        return [
            'reasons_json'      => 'array',
            'is_civic_issue'    => 'boolean',
            'needs_human_review'=> 'boolean',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(ReportSubmission::class, 'submission_id');
    }

    public function analysisRun()
    {
        return $this->belongsTo(AiAnalysisRun::class, 'ai_analysis_run_id');
    }
}
