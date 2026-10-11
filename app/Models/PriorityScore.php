<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriorityScore extends Model
{
    protected $fillable = [
        'report_id',
        'score',
        'band',
        'factor_breakdown_json',
        'rules_version',
    ];

    protected function casts(): array
    {
        return ['factor_breakdown_json' => 'array'];
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
