<?php

namespace Functional\Learning\Listeners;

use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Events\LearningCreated;
use Functional\Learning\Models\CardProgress;

/**
 * Learning a subject puts each of its questions in box 1, due today (FR-041).
 */
class CreateCardsForLearning
{
    public function handle(LearningCreated $event): void
    {
        $learning = $event->learning;
        $today = LeitnerSchedule::todayFor($learning->user->timezone)->toDateString();
        $now = now();

        $cards = $learning->subject->questions()->pluck('id')->map(fn (int $questionId): array => [
            'learning_id' => $learning->getKey(),
            'user_id' => $learning->user_id,
            'question_id' => $questionId,
            'subject_id' => $learning->subject_id,
            'box' => LeitnerSchedule::FIRST_BOX,
            'next_review_on' => $today,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        CardProgress::query()->insert($cards->all());
    }
}
