<?php

use Functional\Learning\Http\Controllers\AuthoredSubjectsSummaryController;
use Functional\Learning\Http\Controllers\SubjectLearnersController;
use Functional\Learning\Rest\Controllers\CardProgressController;
use Functional\Learning\Rest\Controllers\LearningsController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

// Reviewing is personal: every learning route needs an account.
Route::middleware('auth:sanctum')->group(function (): void {
    Rest::resource('learnings', LearningsController::class);
    Rest::resource('card-progress', CardProgressController::class);

    Route::get('learning/authored-subjects-summary', AuthoredSubjectsSummaryController::class)
        ->name('learning.authored-subjects-summary');

    Route::get('learning/subjects/{subject}/learners', SubjectLearnersController::class)
        ->name('learning.subject-learners');
});
