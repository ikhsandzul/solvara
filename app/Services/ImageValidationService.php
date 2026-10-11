<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Server-side file validation: MIME from content (not extension), size, count.
 */
class ImageValidationService
{
    /** Allowed MIME types. */
    private array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    /** Max file size in KB — read from config. */
    private int $maxKb;

    public function __construct()
    {
        $this->maxKb = (int) config('solvara.max_attachment_kb', 10240);
    }

    /**
     * Validate a single file. Returns null if valid, string error message if not.
     */
    public function validate(UploadedFile $file): ?string
    {
        // Detect MIME from content, not extension.
        $detectedMime = $file->getMimeType();

        if (!in_array($detectedMime, $this->allowedMimes, true)) {
            return "Tipe file tidak diizinkan: {$detectedMime}. Gunakan JPEG, PNG, atau WebP.";
        }

        $sizeKb = $file->getSize() / 1024;
        if ($sizeKb > $this->maxKb) {
            return "Ukuran file melebihi batas {$this->maxKb} KB.";
        }

        return null;
    }

    public function detectedMime(UploadedFile $file): string
    {
        return $file->getMimeType() ?? 'application/octet-stream';
    }
}
