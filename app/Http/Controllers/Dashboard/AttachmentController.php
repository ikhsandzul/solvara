<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ReportAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function show(int $id): StreamedResponse
    {
        $attachment = ReportAttachment::findOrFail($id);

        abort_unless(Storage::disk('private')->exists($attachment->path), 404);

        return Storage::disk('private')->download(
            $attachment->path,
            basename($attachment->path),
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream']
        );
    }
}
