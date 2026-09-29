<?php

namespace Functional\Reminders\Database\Seeders;

use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\ReminderScheduler;
use Functional\Users\Models\User;
use Illuminate\Database\Seeder;

/**
 * Email reminders turned on for the first confirmed accounts, at three different times, and a
 * few past reminders to see the spacing. No device: only a browser can subscribe one.
 */
class RemindersSeeder extends Seeder
{
    private const SEND_TIMES = ['08:00', '12:30', '19:00'];

    public function run(ReminderScheduler $scheduler): void
    {
        $learners = User::query()->whereNotNull('email_verified_at')->orderBy('id')->limit(count(self::SEND_TIMES))->get();

        foreach ($learners->values() as $index => $learner) {
            $setting = ReminderSetting::factory()->emailEnabled()->for($learner)->create([
                'send_time' => self::SEND_TIMES[$index],
                'proposal_seen_at' => now()->subDays(10),
                'activated_at' => now()->subDays(10),
            ]);

            foreach ([3, 1] as $daysAgo) {
                ReminderSend::factory()->create([
                    'user_id' => $learner->id,
                    'local_date' => now($learner->timezone)->subDays($daysAgo)->toDateString(),
                    'sent_at' => now()->subDays($daysAgo),
                ]);
            }

            $scheduler->refresh($setting->fresh());
        }
    }
}
