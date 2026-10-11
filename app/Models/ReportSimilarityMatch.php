<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportSimilarityMatch extends Model
{
    protected $fillable = [
        'submission_id',
        'candidate_report_id',
        'distance_m',
        'match_type',
        'confidence_band',
        'reason',
        'review_status',
        'reviewed_by',
        'review_note',
    ];

    public function submission()
    {
        return $this->belongsTo(ReportSubmission::class, 'submission_id');
    }

    public function candidateReport()
    {
        return $this->belongsTo(Report::class, 'candidate_report_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
