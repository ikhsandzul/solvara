<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user     = $request->user();
        $isOfficer = $user && $user->isOfficer();

        $data = [
            'submission_id'  => $this->id,
            'status'         => $this->status,
            'occurred_at'    => $this->occurred_at?->toIso8601String(),
            'received_at'    => $this->received_at?->toIso8601String(),
            'description'    => $this->description,
            'category'       => new CategoryResource($this->whenLoaded('selectedCategory')),
            'location'       => [
                'latitude'     => $this->latitude,
                'longitude'    => $this->longitude,
                'address_text' => $this->address_text,
            ],
            'report_id'      => $this->accepted_report_id,
        ];

        // Show moderation summary (safe fields only, no raw reasons for citizens).
        $moderation = $this->whenLoaded('moderationResult');
        if ($moderation && $moderation->getWrappedObject()) {
            $data['moderation'] = [
                'decision' => $moderation->moderation_decision,
                'category' => $moderation->suggested_category,
            ];
        }

        // Show possible matches for duplicate_review status (public fields only).
        if ($this->status === 'duplicate_review') {
            $matches = $this->whenLoaded('similarityMatches');
            if ($matches) {
                $data['possible_matches'] = $matches->map(fn ($m) => [
                    'report_id'   => (string) $m->candidate_report_id,
                    'distance_m'  => $m->distance_m,
                    'reason'      => $m->reason,
                ])->values()->toArray();
            }
        }

        // Officer-only: expose full moderation reasons and similarity data.
        if ($isOfficer) {
            $data['user_id'] = $this->user_id;
        }

        return $data;
    }
}
