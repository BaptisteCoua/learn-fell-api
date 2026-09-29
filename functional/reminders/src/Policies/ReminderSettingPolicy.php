<?php

namespace Functional\Reminders\Policies;

use Functional\Reminders\Access\Controls\ReminderSettingControl;
use Lomkit\Access\Policies\ControlledPolicy;

class ReminderSettingPolicy extends ControlledPolicy
{
    protected string $control = ReminderSettingControl::class;
}
