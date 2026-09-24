<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\SubjectTag;

class SubjectTagsChanged
{
    public function __construct(public readonly SubjectTag $subjectTag) {}
}
