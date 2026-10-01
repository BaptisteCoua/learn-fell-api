<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Users\Events\AccountDeletionCancelled;

/**
 * Feature 004, FR-015 — the author changed their mind: their withheld subjects are published
 * again, with their first publication date. A subject retired meanwhile stays retired.
 */
class RestoreWithheldSubjects
{
    public function handle(AccountDeletionCancelled $event): void
    {
        Subject::query()
            ->where('author_id', $event->user->getKey())
            ->where('status', SubjectStatus::Withheld)
            ->get()
            ->each(fn (Subject $subject): bool => $subject->forceFill(['status' => SubjectStatus::Published])->save());
    }
}
