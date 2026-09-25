<?php

namespace Functional\Learning\Policies;

use Functional\Learning\Access\Controls\CardProgressControl;
use Lomkit\Access\Policies\ControlledPolicy;

class CardProgressPolicy extends ControlledPolicy
{
    protected string $control = CardProgressControl::class;
}
