<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\Question;

/**
 * A question was added: whoever learns its subject gets it as a new card (FR-051).
 */
class QuestionCreated
{
    public function __construct(public readonly Question $question) {}
}
