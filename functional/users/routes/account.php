<?php

use Functional\Users\Http\Controllers\AccountDeletionController;
use Functional\Users\Http\Controllers\RegisterController;
use Functional\Users\Http\Controllers\ResendVerificationEmailController;
use Functional\Users\Http\Controllers\VerifyEmailController;
use Functional\Users\Support\EmailVerificationLink;
use Illuminate\Support\Facades\Route;

/*
| Account routes next to Fortify's: same `web` session middleware, same `/api` prefix.
*/

Route::post('register', RegisterController::class)->name('users.register');

Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware('throttle:6,1')
    ->whereNumber('id')
    ->name(EmailVerificationLink::ROUTE);

Route::post('email/verification-notification', ResendVerificationEmailController::class)
    ->middleware('throttle:6,1')
    ->name('users.verification.send');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('account/deletion', [AccountDeletionController::class, 'show'])->name('users.deletion.show');
    Route::post('account/deletion', [AccountDeletionController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('users.deletion.store');
});
