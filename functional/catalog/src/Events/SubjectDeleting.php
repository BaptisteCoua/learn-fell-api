<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\Subject;

/**
 * A subject is about to be deleted: each layer removes what it attached to it (questions,
 * tags, learnings, progress, pending reports) — there is no cascade in the database.
 */
class SubjectDeleting
{
    public function __construct(public readonly Subject $subject) {}
}
