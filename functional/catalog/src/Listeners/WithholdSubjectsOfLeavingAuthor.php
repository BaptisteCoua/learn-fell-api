<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Users\Events\AccountDeletionRequested;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * Feature 004, FR-004 and FR-009 — an author with published subjects must say what becomes of
 * them; with « Tout effacer », they are withheld at once. Runs inside the request's transaction,
 * so refusing here undoes the whole request (research R2).
 */
class WithholdSubjectsOfLeavingAuthor
{
    public function handle(AccountDeletionRequested $event): void
    {
        $author = $event->user;
        $publishedSubjects = Subject::query()
            ->where('author_id', $author->getKey())
            ->where('status', SubjectStatus::Published)
            ->get();

        if ($publishedSubjects->isEmpty()) {
            return;
        }

        if ($author->keeps_published_subjects === null) {
            throw new BusinessRuleException('subject_choice_required');
        }

        if ($author->keeps_published_subjects) {
            return;
        }

        // One by one, so the subjects' own saving listeners run; published_at is kept for a
        // cancellation.
        $publishedSubjects->each(fn (Subject $subject): bool => $subject->forceFill(['status' => SubjectStatus::Withheld])->save());
    }
}
