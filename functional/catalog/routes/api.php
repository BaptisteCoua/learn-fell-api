<?php

use Functional\Catalog\Rest\Controllers\CategoriesController;
use Functional\Catalog\Rest\Controllers\QuestionsController;
use Functional\Catalog\Rest\Controllers\SubjectsController;
use Functional\Catalog\Rest\Controllers\TagsController;
use Lomkit\Rest\Facades\Rest;

Rest::resource('categories', CategoriesController::class);
Rest::resource('tags', TagsController::class);
Rest::resource('subjects', SubjectsController::class);
Rest::resource('questions', QuestionsController::class);
