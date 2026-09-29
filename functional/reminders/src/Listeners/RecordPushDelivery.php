<?php

namespace Functional\Reminders\Listeners;

use NotificationChannels\WebPush\Events\NotificationSent;

/**
 * The last reminder a device accepted, shown in its row of the account page (data-model.md).
 * A device that can no longer receive is removed by the channel itself (FR-017).
 */
class RecordPushDelivery
{
    public function handle(NotificationSent $event): void
    {
        $event->subscription->forceFill(['last_delivered_at' => now()])->save();
    }
}
