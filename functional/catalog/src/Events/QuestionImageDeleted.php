<?php

namespace Functional\Catalog\Events;

use Functional\Catalog\Models\QuestionImage;

/**
 * An image row is gone: its files follow once the transaction commits (FR-017).
 */
class QuestionImageDeleted
{
    public function __construct(public readonly QuestionImage $image) {}
}
