<?php

namespace Functional\Reminders\Policies;

use Functional\Reminders\Access\Controls\PushSubscriptionControl;
use Lomkit\Access\Policies\ControlledPolicy;

class PushSubscriptionPolicy extends ControlledPolicy
{
    protected string $control = PushSubscriptionControl::class;
}
