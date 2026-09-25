<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\Question;

/**
 * A question is about to be deleted: the learning layer drops the review progress attached
 * to it first, since nothing cascades in the database.
 */
class QuestionDeleting
{
    public function __construct(public readonly Question $question) {}
}
