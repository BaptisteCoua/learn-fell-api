<?php

namespace Functional\Learning\Queries;

use Functional\Catalog\Enums\SubjectStatus;
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
     */
    public static function constrain(Builder $query, string $timezone, ?array $subjectIds = null): Builder
    {
        $today = LeitnerSchedule::todayFor($timezone)->toDateString();

        return $query
            ->when($subjectIds !== null, fn (Builder $cards): Builder => $cards->whereIn('card_progress.subject_id', $subjectIds))
            ->where('card_progress.next_review_on', '<=', $today)
            ->whereHas('subject', fn (Builder $subjects): Builder => $subjects->where('status', SubjectStatus::Published));
    }
}
