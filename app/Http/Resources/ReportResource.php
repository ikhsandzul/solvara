<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOfficer = $user && $user->isOfficer();

        $data = [
            'id'              => $this->id,
            'public_code'     => $this->public_code,
            'title'           => $this->title,
            'description'     => $this->description,
            'status'          => $this->status,
            'analysis_status' => $this->analysis_status,
            'category'        => new CategoryResource($this->whenLoaded('category')),
            'occurred_at'     => $this->occurred_at?->toIso8601String(),
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
            'attachments'     => AttachmentResource::collection($this->whenLoaded('attachments')),
            'ai_analysis'     => new AiAnalysisResource($this->whenLoaded('latestAiAnalysis')),
            'priority'        => new PriorityScoreResource($this->whenLoaded('priorityScore')),
            'status_history'  => StatusHistoryResource::collection($this->whenLoaded('statusHistories')),
        ];

        // Location: always include for owner/officer.
        $data['location'] = [
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'address_text' => $this->address_text,
        ];

        // Officer-only fields.
        if ($isOfficer) {
            $data['user_id'] = $this->user_id;
        }

        return $data;
    }
}
