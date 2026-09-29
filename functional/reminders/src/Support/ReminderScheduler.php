<?php

namespace Functional\Reminders\Support;

use Carbon\CarbonInterface;
use Functional\Reminders\Domain\NextReminderSlot;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;

/**
 * Keeps `next_reminder_at` in step with the settings (research R4): the next slot while a
 * channel is on, nothing otherwise, so the minute-by-minute command only reads an index.
 */
class ReminderScheduler
{
    public function refresh(ReminderSetting $setting, ?CarbonInterface $now = null): void
    {
        $setting->next_reminder_at = $setting->hasActiveChannel()
            ? NextReminderSlot::next(
                $setting->send_time,
                $setting->user->timezone,
                $now ?? now(),
                $this->lastLocalDate($setting),
            )
            : null;

        $setting->save();
    }

    private function lastLocalDate(ReminderSetting $setting): ?string
    {
        $lastLocalDate = ReminderSend::query()->where('user_id', $setting->user_id)->max('local_date');

        return $lastLocalDate === null ? null : substr((string) $lastLocalDate, 0, 10);
    }
}
