<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Events\SubjectDeleting;

/**
 * Deleting a subject deletes its questions and frees its tags (FR-019). Each question is
 * deleted as a model so that QuestionDeleted reaches the other layers.
 */
class DeleteSubjectQuestions
{
    public function handle(SubjectDeleting $event): void
    {
        $subject = $event->subject;

        $subject->questions()->get()->each->delete();
        $subject->tags()->detach();
    }
}
