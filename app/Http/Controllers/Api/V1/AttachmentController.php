<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReportAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(Request $request, int $id): StreamedResponse
    {
        $attachment = ReportAttachment::with('report')->findOrFail($id);

        if (!$request->user()->can('view', $attachment->report)) {
            abort(403);
        }

        if (!Storage::disk('private')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('private')->response($attachment->path, null, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }
}
