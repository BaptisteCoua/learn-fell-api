<?php

namespace Functional\Learning\Events;

use Functional\Learning\Models\Learning;

class LearningDeleting
{
    public function __construct(public readonly Learning $learning) {}
}
