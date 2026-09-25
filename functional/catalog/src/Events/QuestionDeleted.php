<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\Question;

/**
 * A question is gone: the learning layer drops the review progress attached to it.
 */
class QuestionDeleted
{
    public function __construct(public readonly Question $question) {}
}
