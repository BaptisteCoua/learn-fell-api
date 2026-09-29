<?php

namespace Functional\Reminders\Providers;

use Functional\Reminders\Listeners\CreateReminderSetting;
use Functional\Reminders\Listeners\DeleteRemindersOfUser;
use Functional\Reminders\Listeners\RefreshReminderAfterTimezoneChange;
use Functional\Users\Models\User;
use Illuminate\Support\Facades\Event;
use Xefi\LaravelOSDD\LayerServiceProvider;

class RemindersServiceProvider extends LayerServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->withRouting(
            api: __DIR__.'/../../routes/api.php',
            commands: __DIR__.'/../../routes/console.php',
        );

        // The users layer knows nothing of the reminders: its model events are enough.
        Event::listen('eloquent.created: '.User::class, CreateReminderSetting::class);
        Event::listen('eloquent.updated: '.User::class, RefreshReminderAfterTimezoneChange::class);
        Event::listen('eloquent.deleting: '.User::class, DeleteRemindersOfUser::class);
    }

    public function register(): void
    {
        $this->overrideConfigFrom(__DIR__.'/../../config/webpush.php', 'webpush');
    }
}
