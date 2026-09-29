<?php

namespace Functional\Reminders\Domain;

use Carbon\CarbonImmutable;

/**
 * Reminders space out when the account stops reviewing (research R6): every day for a week,
 * every other day until the 21st day, then once a week. The days without review are counted
 * from the last answer, so any answer brings back the daily rhythm (FR-013). The tiers are
 * defaults, to adjust once the return rate is measured (SC-007).
 */
class ReminderSpacing
{
    /**
     * Days without review => minimum days since the last reminder, from the highest tier down.
     */
    private const TIERS = [
        22 => 7,
        8 => 2,
        0 => 1,
    ];

    /**
     * @param  string  $today  local date, "YYYY-MM-DD"
     * @param  string  $lastActivityDate  local date of the last answer, or of the activation
     * @param  string|null  $lastReminderDate  local date of the last reminder, if any
     */
    public static function allows(string $today, string $lastActivityDate, ?string $lastReminderDate): bool
    {
        if ($lastReminderDate === null) {
            return true;
        }

        $day = CarbonImmutable::parse($today);
        $daysWithoutReview = (int) CarbonImmutable::parse($lastActivityDate)->diffInDays($day);
        $daysSinceReminder = (int) CarbonImmutable::parse($lastReminderDate)->diffInDays($day);

        return $daysSinceReminder >= self::minimumGap($daysWithoutReview);
    }

    private static function minimumGap(int $daysWithoutReview): int
    {
        foreach (self::TIERS as $fromDay => $gap) {
            if ($daysWithoutReview >= $fromDay) {
                return $gap;
            }
        }

        return self::TIERS[0];
    }
}
