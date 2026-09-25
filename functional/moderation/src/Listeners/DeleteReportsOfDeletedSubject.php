<?php

namespace Functional\Moderation\Listeners;

use Functional\Catalog\Events\SubjectDeleting;
use Functional\Moderation\Models\Report;

/**
 * A deleted subject takes its reports along: they point at it and nothing cascades. Its
 * moderation decisions stay in the history, which keeps the subject's title (FR-034).
 */
class DeleteReportsOfDeletedSubject
{
    public function handle(SubjectDeleting $event): void
    {
        Report::query()->where('subject_id', $event->subject->getKey())->delete();
    }
}
