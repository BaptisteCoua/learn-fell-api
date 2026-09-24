<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\Subject;

class SubjectSaving
{
    public function __construct(public readonly Subject $subject) {}
}
