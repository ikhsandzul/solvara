<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status'                => $this->status,
            'category_suggested'    => $this->category_suggested,
            'summary'               => $this->summary,
            'keywords'              => $this->keywords_json,
            'urgency_suggestion'    => $this->urgency_suggestion,
            'confidence_band'       => $this->confidence_band,
            'pattern_insight'       => $this->pattern_insight,
            'recommended_next_step' => $this->recommended_next_step,
            'needs_human_review'    => $this->needs_human_review,
            // related_report_ids and missing_information intentionally omitted from public view.
        ];
    }
}
