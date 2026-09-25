<?php

namespace Functional\Learning\Jobs;

use Functional\Catalog\Models\Question;
use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Adds a new question to every learner of its subject, in box 1, due today (FR-051).
 */
class AddQuestionToLearners implements ShouldQueue
{
    use Queueable;

    // The question may be deleted before the job runs: nothing to add then.
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Question $question) {}

    public function handle(): void
    {
        Learning::query()
            ->with('user')
            ->where('subject_id', $this->question->subject_id)
            ->each(function (Learning $learning): void {
                CardProgress::query()->firstOrCreate(
                    ['user_id' => $learning->user_id, 'question_id' => $this->question->getKey()],
                    [
                        'learning_id' => $learning->getKey(),
                        'subject_id' => $learning->subject_id,
                        'box' => LeitnerSchedule::FIRST_BOX,
                        'next_review_on' => LeitnerSchedule::todayFor($learning->user->timezone)->toDateString(),
                    ],
                );
            });
    }
}
