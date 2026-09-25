<?php

namespace Functional\Learning\Listeners;

use Functional\Catalog\Events\QuestionDeleting;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;

/**
 * A deleted question leaves every learner's review (FR-051).
 */
class RemoveQuestionFromLearners
{
    public function handle(QuestionDeleting $event): void
    {
        $cardIds = CardProgress::query()->where('question_id', $event->question->getKey())->pluck('id');

        ReviewAnswer::query()->whereIn('card_progress_id', $cardIds)->delete();
        CardProgress::query()->whereIn('id', $cardIds)->delete();
    }
}
