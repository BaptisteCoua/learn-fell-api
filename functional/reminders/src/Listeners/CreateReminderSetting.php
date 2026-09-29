<?php

namespace Functional\Reminders\Listeners;

use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;

/**
 * Every account gets its reminder settings when it is created, every channel off (FR-001).
 */
class CreateReminderSetting
{
    public function handle(User $user): void
    {
        ReminderSetting::query()->firstOrCreate(['user_id' => $user->getKey()]);
    }
}
