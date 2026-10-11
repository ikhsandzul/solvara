<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'kind'       => $this->kind,
            'mime_type'  => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            // URL is served through an authorised endpoint, not a direct path.
            'url'        => route('attachments.show', $this->id),
        ];
    }
}
