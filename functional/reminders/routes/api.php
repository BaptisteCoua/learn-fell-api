<?php

use Functional\Reminders\Rest\Controllers\PushSubscriptionsController;
use Functional\Reminders\Rest\Controllers\ReminderSettingsController;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

// The reminders are personal, and sent to a confirmed address only (FR-019).
Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Rest::resource('reminder-settings', ReminderSettingsController::class);
    Rest::resource('push-subscriptions', PushSubscriptionsController::class);
});
