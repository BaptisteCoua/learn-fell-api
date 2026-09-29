<?php

use Functional\Reminders\Http\Controllers\UnsubscribeController;
use Functional\Reminders\Rest\Controllers\PushSubscriptionsController;
use Functional\Reminders\Rest\Controllers\ReminderSettingsController;
use Functional\Reminders\Support\UnsubscribeLink;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

// The reminders are personal, and sent to a confirmed address only (FR-019).
Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Rest::resource('reminder-settings', ReminderSettingsController::class);
    Rest::resource('push-subscriptions', PushSubscriptionsController::class);
});

// From a reminder email, without a session: the web page and the mailboxes (research R7).
Route::post('reminders/unsubscribe/{id}', UnsubscribeController::class)
    ->whereNumber('id')
    ->middleware('throttle:6,1')
    ->name(UnsubscribeLink::ROUTE);
