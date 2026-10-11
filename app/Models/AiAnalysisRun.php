<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysisRun extends Model
{
    protected $fillable = [
        'submission_id',
        'flow_type',
        'flow_id_or_version',
        'provider',
        'model_id',
        'status',
        'attempt_number',
        'duration_ms',
        'response_schema_version',
        'sanitized_output_json',
        'error_code',
    ];

    protected function casts(): array
    {
        return ['sanitized_output_json' => 'array'];
    }

    public function submission()
    {
        return $this->belongsTo(ReportSubmission::class, 'submission_id');
    }
}
