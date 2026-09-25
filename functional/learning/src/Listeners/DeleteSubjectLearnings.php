<?php

namespace Functional\Learning\Listeners;

use Functional\Catalog\Events\SubjectDeleting;
use Functional\Learning\Models\Learning;

/**
 * A deleted subject is no longer learned by anyone (FR-019). Each learning is deleted as a
 * model, so its cards go with it.
 */
class DeleteSubjectLearnings
{
    public function handle(SubjectDeleting $event): void
    {
        Learning::query()->where('subject_id', $event->subject->getKey())->get()->each->delete();
    }
}
