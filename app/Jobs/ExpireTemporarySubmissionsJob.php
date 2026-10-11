<?php

namespace App\Jobs;

use App\Models\ReportSubmission;
use App\Services\PrivateReportMediaStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled job: expire submissions past their TTL and delete temporary photos.
 */
class ExpireTemporarySubmissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PrivateReportMediaStorage $storage): void
    {
        $expired = ReportSubmission::where('expires_at', '<', now())
            ->whereNotIn('status', ['accepted', 'rejected', 'linked_to_existing_report', 'expired'])
            ->get();

        foreach ($expired as $submission) {
            if ($submission->temporary_attachment_path) {
                $storage->deleteTemporary($submission->temporary_attachment_path);
                Log::info('ExpireTemporarySubmissionsJob: deleted temporary photo', [
                    'submission_id' => $submission->id,
                ]);
            }

            $submission->update([
                'status'                    => 'expired',
                'temporary_attachment_path' => null,
            ]);
        }

        Log::info('ExpireTemporarySubmissionsJob: processed', ['count' => $expired->count()]);
    }
}
