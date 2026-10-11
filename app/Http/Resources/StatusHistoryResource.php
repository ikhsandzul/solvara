<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'note'       => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
