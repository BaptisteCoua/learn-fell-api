<?php

namespace Functional\Reminders\Support;

use Functional\Reminders\Enums\EmailDisabledReason;
use Functional\Reminders\Models\ReminderSetting;

/**
 * Refusals answered by the mail server at sending time: the 3rd in a row turns the reminder
 * emails off, and an accepted email starts the count again (FR-018, spec assumptions).
 */
class SmtpBounceRecorder implements RecordsEmailBounce
{
    public const REFUSALS_BEFORE_STOP = 3;

    public function __construct(private readonly ReminderScheduler $scheduler) {}

    public function recordRefusal(ReminderSetting $setting): void
    {
        $setting->email_bounce_count++;

        if ($setting->email_bounce_count >= self::REFUSALS_BEFORE_STOP) {
            $setting->forceFill([
                'email_enabled' => false,
                'email_disabled_reason' => EmailDisabledReason::Bounced,
            ]);
        }

        $this->scheduler->refresh($setting);
    }

    public function recordDelivery(ReminderSetting $setting): void
    {
        if ($setting->email_bounce_count > 0) {
            $setting->forceFill(['email_bounce_count' => 0])->save();
        }
    }
}
