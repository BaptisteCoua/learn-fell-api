<?php

namespace Functional\Reminders\Listeners;

use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;

/**
 * An account takes its reminders with it: devices, log, then settings, since no foreign key
 * cascades (research R13).
 */
class DeleteRemindersOfUser
{
    public function handle(User $user): void
    {
        $setting = ReminderSetting::query()->where('user_id', $user->getKey())->first();

        $setting?->pushSubscriptions()->delete();
        ReminderSend::query()->where('user_id', $user->getKey())->delete();
        $setting?->delete();
    }
}
