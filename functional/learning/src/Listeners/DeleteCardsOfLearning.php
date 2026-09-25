<?php

namespace Functional\Learning\Listeners;

use Functional\Learning\Events\LearningDeleting;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;

/**
 * Stopping to learn a subject forgets its cards and their answers (FR-050).
 */
class DeleteCardsOfLearning
{
    public function handle(LearningDeleting $event): void
    {
        $cardIds = CardProgress::query()->where('learning_id', $event->learning->getKey())->pluck('id');

        ReviewAnswer::query()->whereIn('card_progress_id', $cardIds)->delete();
        CardProgress::query()->whereIn('id', $cardIds)->delete();
    }
}
