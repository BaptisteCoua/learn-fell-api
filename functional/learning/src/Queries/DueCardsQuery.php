<?php

namespace Functional\Learning\Queries;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Models\CardProgress;
use Functional\Users\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * The cards due today (FR-044): due on or before today in the learner's time zone, in a subject
 * still published. An unpublished subject pauses its cards without losing them (research R8).
 * The review session and the reminders of 002 share this rule, so a reminder announces the
 * cards its session shows.
 */
class DueCardsQuery
{
    /**
     * @param  list<int>|null  $subjectIds  every subject of the learner when null
     * @return Builder<CardProgress>
     */
    public static function forUser(User $user, ?array $subjectIds = null): Builder
    {
        return self::constrain(
            CardProgress::query()->where('card_progress.user_id', $user->getKey()),
            $user->timezone,
            $subjectIds,
        );
    }

    /**
     * @param  list<int>|null  $subjectIds
     * @param  int  $daysAhead  0 for the cards due today, more for those due within that many days
     */
    public static function constrain(Builder $query, string $timezone, ?array $subjectIds = null, int $daysAhead = 0): Builder
    {
        $lastDueDate = LeitnerSchedule::todayFor($timezone)->addDays($daysAhead)->toDateString();

        return $query
            ->when($subjectIds !== null, fn (Builder $cards): Builder => $cards->whereIn('card_progress.subject_id', $subjectIds))
            ->where('card_progress.next_review_on', '<=', $lastDueDate)
            ->whereHas('subject', fn (Builder $subjects): Builder => $subjects->where('status', SubjectStatus::Published));
    }

    /**
     * The order of a review session: the most overdue first, then each subject in its question
     * order.
     */
    public static function inReviewOrder(Builder $query): Builder
    {
        return $query
            ->reorder()
            ->orderBy('card_progress.next_review_on')
            ->orderBy('card_progress.subject_id')
            ->orderBy(Question::query()->select('position')->whereColumn('questions.id', 'card_progress.question_id'));
    }
}
