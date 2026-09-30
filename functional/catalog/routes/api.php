<?php

use Functional\Catalog\Http\Controllers\ShowQuestionImageController;
use Functional\Catalog\Http\Controllers\StoreQuestionImageController;
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

// Visitors see the images of published subjects: the session is read, not required.
Route::get('question-images/{id}/{width}', ShowQuestionImageController::class)
    ->whereNumber(['id', 'width']);
