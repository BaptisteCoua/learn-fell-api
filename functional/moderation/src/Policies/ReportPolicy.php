<?php

namespace Functional\Moderation\Policies;

use Functional\Moderation\Access\Controls\ReportControl;
use Lomkit\Access\Policies\ControlledPolicy;

class ReportPolicy extends ControlledPolicy
{
    protected string $control = ReportControl::class;
}
