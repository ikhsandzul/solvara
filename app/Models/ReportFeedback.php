<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportFeedback extends Model
{
    protected $fillable = ['report_id', 'user_id', 'type', 'message'];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
