<?php

namespace Functional\Learning\Events;

use Functional\Learning\Models\Learning;

class LearningCreated
{
    public function __construct(public readonly Learning $learning) {}
}
