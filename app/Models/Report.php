<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'public_code',
        'user_id',
        'category_id',
        'title',
        'description',
        'latitude',
        'longitude',
        'address_text',
        'occurred_at',
        'status',
        'analysis_status',
        'accepted_from_submission_id',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function attachments()
    {
        return $this->hasMany(ReportAttachment::class);
    }

    public function aiAnalyses()
    {
        return $this->hasMany(AiAnalysis::class);
    }

    public function latestAiAnalysis()
    {
        return $this->hasOne(AiAnalysis::class)->orderByDesc('id');
    }

    public function priorityScore()
    {
        return $this->hasOne(PriorityScore::class)->orderByDesc('id');
    }

    public function statusHistories()
    {
        return $this->hasMany(ReportStatusHistory::class)->orderBy('created_at');
    }

    public function verificationReviews()
    {
        return $this->hasMany(VerificationReview::class);
    }

    public function feedback()
    {
        return $this->hasMany(ReportFeedback::class);
    }

    /**
     * Valid status transition map.
     */
    public static function allowedTransitions(): array
    {
        return [
            'submitted'                      => ['needs_review', 'accepted', 'rejected'],
            'needs_review'                   => ['accepted', 'rejected'],
            'accepted'                       => ['in_progress', 'rejected'],
            'in_progress'                    => ['resolved_pending_verification'],
            'resolved_pending_verification'  => ['verified', 'reopened'],
            'verified'                       => ['reopened'],
            'reopened'                       => ['needs_review', 'accepted', 'in_progress', 'rejected'],
            'rejected'                       => ['reopened'],
        ];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return in_array($newStatus, static::allowedTransitions()[$this->status] ?? []);
    }
}
