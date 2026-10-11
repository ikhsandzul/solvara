<?php

namespace App\Models;

use Database\Factories\ReportSubmissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSubmission extends Model
{
    use HasFactory, HasUlids;

    /** @use HasFactory<ReportSubmissionFactory> */

    protected $fillable = [
        'user_id',
        'description',
        'selected_category_id',
        'latitude',
        'longitude',
        'address_text',
        'occurred_at',
        'received_at',
        'status',
        'temporary_attachment_path',
        'detected_mime',
        'size_bytes',
        'sha256',
        'gps_accuracy_m',
        'idempotency_key',
        'expires_at',
        'accepted_report_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'expires_at'  => 'datetime',
            'latitude'    => 'decimal:7',
            'longitude'   => 'decimal:7',
        ];
    }

    // Valid status set
    public const STATUSES = [
        'received',
        'queued_for_moderation',
        'moderating',
        'queued_for_duplicate_check',
        'checking_duplicates',
        'duplicate_review',
        'manual_review',
        'accepted',
        'rejected',
        'linked_to_existing_report',
        'processing_failed',
        'expired',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function selectedCategory()
    {
        return $this->belongsTo(Category::class, 'selected_category_id');
    }

    public function acceptedReport()
    {
        return $this->belongsTo(Report::class, 'accepted_report_id');
    }

    public function aiAnalysisRuns()
    {
        return $this->hasMany(AiAnalysisRun::class, 'submission_id');
    }

    public function moderationResult()
    {
        return $this->hasOne(ReportModerationResult::class, 'submission_id')->orderByDesc('id');
    }

    public function similarityMatches()
    {
        return $this->hasMany(ReportSimilarityMatch::class, 'submission_id');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
