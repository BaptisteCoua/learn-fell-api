<?php

use Functional\Users\Http\Controllers\CurrentUserController;
use Illuminate\Support\Facades\Route;

Route::get('user', CurrentUserController::class)->middleware('auth:sanctum')->name('users.current');
