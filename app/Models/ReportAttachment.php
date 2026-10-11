<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportAttachment extends Model
{
    protected $fillable = ['report_id', 'path', 'kind', 'mime_type', 'size_bytes', 'sha256'];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
