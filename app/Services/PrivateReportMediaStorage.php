<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles private storage for report photo attachments.
 * Quarantine = temporary path during submission review.
 * Permanent = path after acceptance.
 */
class PrivateReportMediaStorage
{
    private string $tempDisk;
    private string $permanentDisk;

    public function __construct()
    {
        $this->tempDisk      = config('solvara.report_temp_disk', 'private');
        $this->permanentDisk = config('solvara.report_permanent_disk', 'private');
    }

    /**
     * Store uploaded file in quarantine. Returns the storage path.
     */
    public function storeTemporary(UploadedFile $file): string
    {
        $ext  = match ($file->getMimeType()) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $path = 'submissions/quarantine/' . Str::uuid() . '.' . $ext;
        Storage::disk($this->tempDisk)->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Move file from quarantine to permanent storage. Returns new path.
     */
    public function moveToPermament(string $tempPath, string $reportId): string
    {
        $filename   = basename($tempPath);
        $newPath    = "reports/{$reportId}/{$filename}";
        $content    = Storage::disk($this->tempDisk)->get($tempPath);

        Storage::disk($this->permanentDisk)->put($newPath, $content);
        Storage::disk($this->tempDisk)->delete($tempPath);

        return $newPath;
    }

    /**
     * Delete a temporary file. Logs path without content.
     */
    public function deleteTemporary(string $path): void
    {
        if (Storage::disk($this->tempDisk)->exists($path)) {
            Storage::disk($this->tempDisk)->delete($path);
        }
    }

    public function tempDisk(): string
    {
        return $this->tempDisk;
    }
}
