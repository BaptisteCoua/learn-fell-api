<?php

use Functional\Moderation\Rest\Controllers\ModerationDecisionsController;
use Functional\Moderation\Rest\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

// Reporting and moderating need an account.
Route::middleware('auth:sanctum')->group(function (): void {
    Rest::resource('reports', ReportsController::class);
    Rest::resource('moderation-decisions', ModerationDecisionsController::class);
});
