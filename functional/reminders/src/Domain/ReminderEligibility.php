<?php

namespace Functional\Reminders\Domain;

use Carbon\CarbonImmutable;
use Functional\Learning\Models\Learning;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Queries\DueCardsQuery;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;

/**
 * Whether an account gets a reminder today, and what it announces (research R5). The cards
 * are counted with the rule of the review session, so the reminder and its session agree
 * (SC-006).
 */
class ReminderEligibility
{
    public function for(ReminderSetting $setting): ?DueReminder
    {
        $learner = $setting->user;

        if (! $learner->hasVerifiedEmail() || ! $setting->hasActiveChannel()) {
            return null;
        }

        if (! Learning::query()->where('user_id', $learner->getKey())->exists()) {
            return null;
        }

        /** @var array<int, int> $dueCardsBySubject */
        $dueCardsBySubject = DueCardsQuery::forUser($learner)
            ->toBase()
            ->selectRaw('card_progress.subject_id, count(*) as due_count')
            ->groupBy('card_progress.subject_id')
            ->orderBy('card_progress.subject_id')
            ->pluck('due_count', 'subject_id')
            ->all();

        if ($dueCardsBySubject === [] || ! $this->spacingAllowsToday($setting)) {
            return null;
        }

        return new DueReminder(
            (int) array_sum($dueCardsBySubject),
            array_map(intval(...), array_keys($dueCardsBySubject)),
        );
    }

    /**
     * The days without review count from the last answer, or from the activation of an account
     * that never answered (FR-012), in the account's time zone.
     */
    private function spacingAllowsToday(ReminderSetting $setting): bool
    {
        $timezone = $setting->user->timezone;
        $lastAnswerAt = ReviewAnswer::query()->where('user_id', $setting->user_id)->max('answered_at');
        $lastActivity = $lastAnswerAt !== null
            ? CarbonImmutable::parse($lastAnswerAt)
            : ($setting->activated_at ?? now());
        $lastReminderDate = ReminderSend::query()->where('user_id', $setting->user_id)->max('local_date');

        return ReminderSpacing::allows(
            now($timezone)->toDateString(),
            CarbonImmutable::instance($lastActivity)->setTimezone($timezone)->toDateString(),
            $lastReminderDate === null ? null : substr((string) $lastReminderDate, 0, 10),
        );
    }
}
