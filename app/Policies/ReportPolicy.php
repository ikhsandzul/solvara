<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /** Citizen views own report; officer/admin views any. */
    public function view(User $user, Report $report): bool
    {
        if ($user->isOfficer()) {
            return true;
        }

        return $report->user_id === $user->id;
    }

    /** Only citizen can create. */
    public function create(User $user): bool
    {
        return $user->isCitizen();
    }

    /** Officer/admin can change status. */
    public function updateStatus(User $user, Report $report): bool
    {
        return $user->isOfficer();
    }

    /** Officer/admin can verify. */
    public function verify(User $user, Report $report): bool
    {
        return $user->isOfficer();
    }

    /** Citizen can give feedback on own report. */
    public function feedback(User $user, Report $report): bool
    {
        return $report->user_id === $user->id;
    }
}
