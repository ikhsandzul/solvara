<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an official report from an accepted submission, or rejects/links it.
 */
class FinalizeReportSubmissionService
{
    public function __construct(
        private PrivateReportMediaStorage $storage,
        private ReportStatusService $statusService
    ) {}

    /**
     * Promote submission to an official report. Moves photo to permanent storage.
     * Returns the created Report.
     * @throws \DomainException if submission not in an acceptable state.
     */
    public function accept(ReportSubmission $submission, int $actorId, ?string $note = null): Report
    {
        if (!in_array($submission->status, ['queued_for_duplicate_check', 'manual_review', 'moderating'], true)) {
            throw new \DomainException("Submission status '{$submission->status}' cannot be accepted directly.");
        }

        return DB::transaction(function () use ($submission, $actorId, $note) {
            // Build public code.
            $year  = date('Y');
            $count = Report::whereYear('created_at', $year)->count() + 1;
            $code  = 'SOL-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);

            $report = Report::create([
                'public_code'                 => $code,
                'user_id'                     => $submission->user_id,
                'category_id'                 => $submission->selected_category_id,
                'title'                       => null,
                'description'                 => $submission->description,
                'latitude'                    => $submission->latitude,
                'longitude'                   => $submission->longitude,
                'address_text'                => $submission->address_text,
                'occurred_at'                 => $submission->occurred_at,
                'status'                      => 'submitted',
                'analysis_status'             => 'queued',
                'accepted_from_submission_id' => $submission->id,
            ]);

            // Move photo from quarantine to permanent storage.
            if ($submission->temporary_attachment_path) {
                $newPath = $this->storage->moveToPermament(
                    $submission->temporary_attachment_path,
                    (string) $report->id
                );

                ReportAttachment::create([
                    'report_id'  => $report->id,
                    'path'       => $newPath,
                    'kind'       => 'initial',
                    'mime_type'  => $submission->detected_mime ?? 'image/jpeg',
                    'size_bytes' => $submission->size_bytes ?? 0,
                    'sha256'     => $submission->sha256,
                ]);
            }

            // Write status history entry for the new report.
            $report->statusHistories()->create([
                'actor_id'   => $actorId,
                'old_status' => '',
                'new_status' => 'submitted',
                'note'       => $note ?? 'Submission accepted and report created.',
                'created_at' => now(),
            ]);

            // Update submission.
            $submission->update([
                'status'             => 'accepted',
                'accepted_report_id' => $report->id,
            ]);

            return $report;
        });
    }

    /**
     * Reject a submission. Schedules temporary photo for deletion.
     */
    public function reject(ReportSubmission $submission, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($submission, $actorId, $reason) {
            $submission->update(['status' => 'rejected']);
            // Photo cleanup happens via ExpireTemporarySubmissionsJob or immediate delete.
            if ($submission->temporary_attachment_path) {
                $this->storage->deleteTemporary($submission->temporary_attachment_path);
                $submission->update(['temporary_attachment_path' => null]);
            }
        });
    }

    /**
     * Link submission to an existing report (citizen confirms same issue).
     */
    public function linkToExisting(ReportSubmission $submission, string $existingReportId): void
    {
        $submission->update([
            'status'             => 'linked_to_existing_report',
            'accepted_report_id' => $existingReportId,
        ]);
        // Cleanup temp photo since submission is linked, not promoted.
        if ($submission->temporary_attachment_path) {
            $this->storage->deleteTemporary($submission->temporary_attachment_path);
            $submission->update(['temporary_attachment_path' => null]);
        }
    }
}
