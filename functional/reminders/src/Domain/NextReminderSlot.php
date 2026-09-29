<?php

namespace Functional\Reminders\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * When the next reminder of an account is due, as a UTC instant (research R4): the chosen
 * local time on the first local day after the last reminder that is still to come.
 *
 * A local time skipped by summer time comes an hour later, as PHP resolves it. A local time
 * repeated by winter time is its first occurrence, whereas PHP picks the second one.
 */
class NextReminderSlot
{
    /**
     * @param  string  $sendTime  "HH:MM"
     * @param  string|null  $lastLocalDate  local date of the last reminder sent, "YYYY-MM-DD"
     */
    public static function next(string $sendTime, string $timezone, CarbonInterface $now, ?string $lastLocalDate): CarbonImmutable
    {
        $day = CarbonImmutable::instance($now)->setTimezone($timezone)->startOfDay();

        // Never a second reminder on a local date that already had one, even after a move west.
        if ($lastLocalDate !== null && $lastLocalDate >= $day->toDateString()) {
            $day = CarbonImmutable::parse($lastLocalDate, $timezone)->addDay();
        }

        $slot = self::at($day, $sendTime, $timezone);

        if ($slot->lessThanOrEqualTo($now)) {
            $slot = self::at($day->addDay(), $sendTime, $timezone);
        }

        return $slot->utc();
    }

    private static function at(CarbonImmutable $day, string $sendTime, string $timezone): CarbonImmutable
    {
        $slot = CarbonImmutable::createFromFormat('Y-m-d H:i', $day->toDateString().' '.$sendTime, $timezone);

        return self::firstOccurrence($slot);
    }

    /**
     * The same local time earlier in the day, when the clocks went back just before it.
     */
    private static function firstOccurrence(CarbonImmutable $slot): CarbonImmutable
    {
        $offsetBefore = $slot->subHours(3)->getOffset();
        $clocksWentBack = $offsetBefore - $slot->getOffset();

        if ($clocksWentBack <= 0) {
            return $slot;
        }

        $earlier = $slot->subSeconds($clocksWentBack);

        return $earlier->format('Y-m-d H:i') === $slot->format('Y-m-d H:i') ? $earlier : $slot;
    }
}
