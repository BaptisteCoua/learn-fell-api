<?php

namespace Functional\Reminders\Database\Factories;

use Functional\Reminders\Enums\EmailDisabledReason;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReminderSetting>
 */
class ReminderSettingFactory extends Factory
{
    protected $model = ReminderSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'email_enabled' => false,
            'send_time' => ReminderSetting::DEFAULT_SEND_TIME,
        ];
    }

    /**
     * Every account gets its settings when it is created, so the factory fills in that row
     * instead of inserting a second one for the same account.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ReminderSetting $setting): void {
            $existingId = ReminderSetting::query()->where('user_id', $setting->user_id)->value('id');

            if ($existingId !== null) {
                $setting->setAttribute('id', $existingId);
                $setting->exists = true;
            }
        });
    }

    public function emailEnabled(): static
    {
        return $this->state(['email_enabled' => true, 'activated_at' => now()]);
    }

    public function unsubscribed(): static
    {
        return $this->state(['email_enabled' => false, 'activated_at' => now(), 'email_disabled_reason' => EmailDisabledReason::Unsubscribed]);
    }

    public function bounced(): static
    {
        return $this->state([
            'email_enabled' => false,
            'activated_at' => now(),
            'email_disabled_reason' => EmailDisabledReason::Bounced,
            'email_bounce_count' => 3,
        ]);
    }
}
