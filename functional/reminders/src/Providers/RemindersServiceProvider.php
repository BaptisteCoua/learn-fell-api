<?php

namespace Functional\Reminders\Providers;

use Functional\Reminders\Access\Controls\PushSubscriptionControl;
use Functional\Reminders\Access\Controls\ReminderSettingControl;
use Functional\Reminders\Listeners\CreateReminderSetting;
use Functional\Reminders\Listeners\DeleteRemindersOfUser;
use Functional\Reminders\Listeners\RefreshReminderAfterTimezoneChange;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Policies\PushSubscriptionPolicy;
use Functional\Reminders\Policies\ReminderSettingPolicy;
use Functional\Users\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
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

        (new Access)->addControls([new ReminderSettingControl, new PushSubscriptionControl]);

        Gate::policy(ReminderSetting::class, ReminderSettingPolicy::class);
        Gate::policy(PushSubscription::class, PushSubscriptionPolicy::class);

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
