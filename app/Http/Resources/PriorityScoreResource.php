<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriorityScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'score'         => $this->score,
            'band'          => $this->band,
            'rules_version' => $this->rules_version,
            // factor_breakdown is officer-only; callers strip it for public views.
        ];
    }
}
