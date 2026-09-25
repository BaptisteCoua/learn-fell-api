<?php

use Functional\Learning\Rest\Controllers\CardProgressController;
use Functional\Learning\Rest\Controllers\LearningsController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

// Reviewing is personal: every learning route needs an account.
Route::middleware('auth:sanctum')->group(function (): void {
    Rest::resource('learnings', LearningsController::class);
    Rest::resource('card-progress', CardProgressController::class);
});
