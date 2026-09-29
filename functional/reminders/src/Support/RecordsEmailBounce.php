<?php

namespace Functional\Reminders\Support;

use Functional\Reminders\Models\ReminderSetting;

/**
 * Keeps count of the definite refusals of an address (FR-018). The SMTP answer at sending time
 * is the only source for now; the webhook of the production mail provider will report the
 * delayed refusals (DSN) through the same contract (research R8).
 */
interface RecordsEmailBounce
{
    public function recordRefusal(ReminderSetting $setting): void;

    public function recordDelivery(ReminderSetting $setting): void;
}
