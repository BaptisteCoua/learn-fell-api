<?php

namespace Functional\Catalog\Listeners;

use Functional\Catalog\Events\QuestionDeleting;

/**
 * Deleting a question deletes its images (FR-017), and so does deleting a subject, which
 * deletes its questions. Each image is deleted as a model so that its files follow.
 */
class DeleteQuestionImages
{
    public function handle(QuestionDeleting $event): void
    {
        $event->question->images()->get()->each->delete();
    }
}
