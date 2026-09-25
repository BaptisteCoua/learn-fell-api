<?php

namespace Functional\Learning\Policies;

use Functional\Learning\Access\Controls\LearningControl;
use Lomkit\Access\Policies\ControlledPolicy;

class LearningPolicy extends ControlledPolicy
{
    protected string $control = LearningControl::class;
}
