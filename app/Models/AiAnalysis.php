<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysis extends Model
{
    protected $fillable = [
        'report_id',
        'flow_version',
        'provider',
        'model_id',
        'category_suggested',
        'summary',
        'keywords_json',
        'urgency_suggestion',
        'confidence_band',
        'related_report_ids_json',
        'pattern_insight',
        'recommended_next_step',
        'missing_information_json',
        'needs_human_review',
        'status',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'related_report_ids_json' => 'array',
            'missing_information_json' => 'array',
            'needs_human_review' => 'boolean',
        ];
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
