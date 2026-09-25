<?php

namespace Functional\Learning\Domain;

use Carbon\CarbonImmutable;

/**
 * The Leitner rule (FR-046, research R6): a known card moves up one box, at most box 5; a
 * missed card goes back to box 1. The arrival box sets the next review: 1, 2, 4, 8 or 16 days.
 */
final class LeitnerSchedule
{
    public const FIRST_BOX = 1;

    public const LAST_BOX = 5;

    /**
     * Days until the next review, by arrival box.
     */
    public const INTERVAL_DAYS = [1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16];

    public static function arrivalBox(int $box, bool $known): int
    {
        return $known ? min($box + 1, self::LAST_BOX) : self::FIRST_BOX;
    }

    /**
     * @param  CarbonImmutable  $today  the learner's today, in their own time zone
     */
    public static function nextReviewOn(int $arrivalBox, CarbonImmutable $today): CarbonImmutable
    {
        return $today->startOfDay()->addDays(self::INTERVAL_DAYS[$arrivalBox]);
    }

    /**
     * The learner's current day, which is when their cards fall due.
     */
    public static function todayFor(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::now($timezone)->startOfDay();
    }
}
