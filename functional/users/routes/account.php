<?php

use Functional\Users\Http\Controllers\ResendVerificationEmailController;
use Functional\Users\Http\Controllers\VerifyEmailController;
use Functional\Users\Support\EmailVerificationLink;
use Illuminate\Support\Facades\Route;

/*
| Account routes next to Fortify's: same `web` session middleware, same `/api` prefix.
*/

Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware('throttle:6,1')
    ->whereNumber('id')
    ->name(EmailVerificationLink::ROUTE);

Route::post('email/verification-notification', ResendVerificationEmailController::class)
    ->middleware('throttle:6,1')
    ->name('users.verification.send');
