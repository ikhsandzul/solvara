<?php

use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\AttachmentController;
use App\Http\Middleware\OfficerMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => redirect()->route('dashboard.login'));

// ── Dashboard Auth ────────────────────────────────────────────────────
Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // ── Protected officer pages ───────────────────────────────────────
    Route::middleware(['auth', OfficerMiddleware::class])->group(function () {
        Route::get('/', fn() => redirect()->route('dashboard.home'))->name('index');
        Route::get('/home', \App\Livewire\DashboardOverview::class)->name('home');
        Route::get('/reports', \App\Livewire\ReportsList::class)->name('reports');
        Route::get('/reports/{id}', \App\Livewire\ReportDetail::class)->name('reports.show');
        Route::get('/moderation', \App\Livewire\ModerationQueue::class)->name('moderation');
        Route::get('/duplicates', \App\Livewire\DuplicateQueue::class)->name('duplicates');
        Route::get('/verification', \App\Livewire\ResolutionVerification::class)->name('verification');
        Route::get('/attachments/{id}', [AttachmentController::class, 'show'])->name('attachment.show');
    });
});
