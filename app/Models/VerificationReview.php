<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationReview extends Model
{
    public $timestamps = false;

    protected $fillable = ['report_id', 'reviewer_id', 'decision', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
