<?php

namespace App\Providers;

use App\Models\Report;
use App\Models\User;
use App\Policies\ReportPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Officer gate: passes for role officer or admin.
        Gate::define('officer', fn (User $user) => $user->isOfficer());

        // Model policies.
        Gate::policy(Report::class, ReportPolicy::class);
    }
}
