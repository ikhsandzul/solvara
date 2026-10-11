<?php

use App\Jobs\ExpireTemporarySubmissionsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule temporary submission cleanup every hour.
Schedule::job(new ExpireTemporarySubmissionsJob)->hourly();
