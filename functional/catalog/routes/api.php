<?php

use Functional\Catalog\Http\Controllers\PreviewQuestionImportController;
use Functional\Catalog\Http\Controllers\QuestionImportTemplateController;
use Functional\Catalog\Http\Controllers\ShowQuestionImageController;
use Functional\Catalog\Http\Controllers\StoreQuestionImageController;
use Functional\Catalog\Http\Controllers\StoreQuestionImportController;
use Functional\Catalog\Rest\Controllers\CategoriesController;
use Functional\Catalog\Rest\Controllers\QuestionImagesController;
use Functional\Catalog\Rest\Controllers\QuestionsController;
use Functional\Catalog\Rest\Controllers\SubjectsController;
use Functional\Catalog\Rest\Controllers\TagsController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

Rest::resource('categories', CategoriesController::class);
Rest::resource('tags', TagsController::class);
Rest::resource('subjects', SubjectsController::class);
Rest::resource('questions', QuestionsController::class);
Rest::resource('question-images', QuestionImagesController::class);

// A file and a binary stream, which lomkit does not carry (research R5, R8).
Route::post('question-images', StoreQuestionImageController::class)
    ->middleware(['auth:sanctum', 'verified', 'throttle:30,1']);

// A file and a preview computed from it, which lomkit does not carry (specs/008-question-import, R9).
Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::post('subjects/{subject}/question-import/preview', PreviewQuestionImportController::class)
        ->middleware('throttle:30,1');
    Route::post('subjects/{subject}/question-import', StoreQuestionImportController::class)
        ->middleware('throttle:10,1');
});

// A binary stream, opened by a link of the web app (specs/008-question-import, R10).
Route::get('question-import/template', QuestionImportTemplateController::class)
    ->middleware('throttle:30,1');

// Visitors see the images of published subjects: the session is read, not required.
Route::get('question-images/{id}/{width}', ShowQuestionImageController::class)
    ->whereNumber(['id', 'width']);
