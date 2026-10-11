<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\DB;

class ReportStatusService
{
    /**
     * Transition report to $newStatus. Writes history in a transaction.
     * Throws \DomainException when transition is not allowed.
     */
    public function transition(Report $report, string $newStatus, int $actorId, ?string $note = null): void
    {
        if (!$report->canTransitionTo($newStatus)) {
            throw new \DomainException(
                "Transisi dari '{$report->status}' ke '{$newStatus}' tidak diizinkan."
            );
        }

        DB::transaction(function () use ($report, $newStatus, $actorId, $note) {
            $oldStatus = $report->status;
            $report->status = $newStatus;
            $report->save();

            $report->statusHistories()->create([
                'actor_id'   => $actorId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note'       => $note,
                'created_at' => now(),
            ]);
        });
    }
}
