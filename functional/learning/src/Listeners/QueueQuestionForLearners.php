<?php

namespace Functional\Learning\Listeners;

use Functional\Catalog\Events\QuestionCreated;
use Functional\Learning\Jobs\AddQuestionToLearners;

/**
 * A new question reaches its subject's learners in the background: a popular subject may
 * have many of them.
 */
class QueueQuestionForLearners
{
    public function handle(QuestionCreated $event): void
    {
        AddQuestionToLearners::dispatch($event->question);
    }
}
