<?php

namespace Functional\Moderation\Policies;

use Functional\Moderation\Access\Controls\ModerationDecisionControl;
use Lomkit\Access\Policies\ControlledPolicy;

class ModerationDecisionPolicy extends ControlledPolicy
{
    protected string $control = ModerationDecisionControl::class;
}
