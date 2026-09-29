<?php

namespace Functional\Reminders\Listeners;

use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\ReminderScheduler;
use Functional\Users\Models\User;

/**
 * The reminder follows the account's time zone, updated at each login (research R4).
 */
class RefreshReminderAfterTimezoneChange
{
    public function __construct(private readonly ReminderScheduler $scheduler) {}

    public function handle(User $user): void
    {
        if (! $user->wasChanged('timezone')) {
            return;
        }

        $setting = ReminderSetting::query()->where('user_id', $user->getKey())->first();

        if ($setting !== null) {
            $setting->setRelation('user', $user);
            $this->scheduler->refresh($setting);
        }
    }
}
