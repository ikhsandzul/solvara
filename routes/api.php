<?php

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\Officer\OfficerReportController;
use App\Http\Controllers\Api\V1\Officer\OfficerSubmissionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportSubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Solvara v1/v2
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Categories
        Route::get('categories', [CategoryController::class, 'index']);

        // ---- v2: Submission pipeline ----
        Route::post('report-submissions', [ReportSubmissionController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('report-submissions/{id}', [ReportSubmissionController::class, 'show']);
        Route::post('report-submissions/{id}/duplicate-response', [ReportSubmissionController::class, 'duplicateResponse'])
            ->middleware('throttle:10,1');

        // ---- v1 (kept for compatibility): direct report endpoints ----
        Route::post('reports', [ReportController::class, 'store'])
            ->middleware('throttle:20,1');
        Route::get('reports', [ReportController::class, 'index']);
        Route::get('reports/{id}', [ReportController::class, 'show']);
        Route::post('reports/{id}/feedback', [ReportController::class, 'storeFeedback'])
            ->middleware('throttle:5,1');

        // Attachment download (authorised)
        Route::get('attachments/{id}', [AttachmentController::class, 'show'])
            ->name('attachments.show');

        // Officer routes
        Route::middleware('can:officer')->prefix('officer')->group(function () {
            Route::get('dashboard/summary', [OfficerReportController::class, 'dashboard']);
            Route::get('reports', [OfficerReportController::class, 'index']);
            Route::patch('reports/{id}/status', [OfficerReportController::class, 'updateStatus']);
            Route::post('reports/{id}/verification', [OfficerReportController::class, 'verification']);
            // v2: officer submission review
            Route::post('report-submissions/{id}/review', [OfficerSubmissionController::class, 'review']);
        });
    });
});
