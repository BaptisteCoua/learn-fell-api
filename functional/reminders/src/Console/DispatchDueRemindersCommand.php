<?php

namespace Functional\Reminders\Console;

use Carbon\CarbonImmutable;
use Functional\Reminders\Jobs\SendReviewReminder;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\ReminderScheduler;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Run every minute: queues the reminder of each account whose time has come, read from the
 * `next_reminder_at` index, then moves that account on to its next slot (research R4).
 */
class DispatchDueRemindersCommand extends Command
{
    protected $signature = 'reminders:dispatch
        {--now= : replay a given minute, in UTC ("2026-09-26 17:00"), and send right away}';

    protected $description = 'Send the review reminders whose time has come';

    public function handle(ReminderScheduler $scheduler): int
    {
        $replayedMinute = $this->option('now');
        $clockBefore = Date::getTestNow();

        if (is_string($replayedMinute)) {
            Date::setTestNow(CarbonImmutable::parse($replayedMinute, 'UTC'));
        }

        try {
            $this->dispatchDue($scheduler, sendRightAway: is_string($replayedMinute));
        } finally {
            Date::setTestNow($clockBefore);
        }

        return self::SUCCESS;
    }

    private function dispatchDue(ReminderScheduler $scheduler, bool $sendRightAway): void
    {
        $now = now();

        ReminderSetting::query()
            ->with('user')
            ->whereNotNull('next_reminder_at')
            ->where('next_reminder_at', '<=', $now)
            ->chunkById(200, function (Collection $settings) use ($scheduler, $now, $sendRightAway): void {
                foreach ($settings as $setting) {
                    $scheduler->refresh($setting, $now);

                    if ($setting->next_reminder_at === null) {
                        continue;
                    }

                    $sendRightAway
                        ? SendReviewReminder::dispatchSync($setting->id)
                        : SendReviewReminder::dispatch($setting->id);
                }
            });
    }
}
