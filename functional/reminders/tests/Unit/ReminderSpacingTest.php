<?php

namespace Functional\Reminders\Tests\Unit;

use Carbon\CarbonImmutable;
use Functional\Reminders\Domain\ReminderSpacing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FR-012, FR-013, SC-005 — reminders space out when the account stops reviewing.
 */
class ReminderSpacingTest extends TestCase
{
    private const LAST_ANSWER = '2026-09-01';

    private function day(int $offset): string
    {
        return CarbonImmutable::parse(self::LAST_ANSWER)->addDays($offset)->toDateString();
    }

    public function test_every_day_of_inactivity_from_the_1st_to_the_60th(): void
    {
        $lastReminder = null;
        $reminderDays = [];

        for ($inactiveDays = 1; $inactiveDays <= 60; $inactiveDays++) {
            if (ReminderSpacing::allows($this->day($inactiveDays), self::LAST_ANSWER, $lastReminder)) {
                $reminderDays[] = $inactiveDays;
                $lastReminder = $this->day($inactiveDays);
            }
        }

        $this->assertSame(
            [1, 2, 3, 4, 5, 6, 7, 9, 11, 13, 15, 17, 19, 21, 28, 35, 42, 49, 56],
            $reminderDays,
        );
    }

    /**
     * @return array<string, array{int, int|null, bool}>
     */
    public static function tiers(): array
    {
        return [
            'first reminder ever' => [40, null, true],
            'reviewed today, reminded yesterday' => [0, 1, true],
            'day 7, reminded yesterday' => [7, 1, true],
            'day 8, reminded yesterday' => [8, 1, false],
            'day 8, reminded 2 days ago' => [8, 2, true],
            'day 21, reminded yesterday' => [21, 1, false],
            'day 21, reminded 2 days ago' => [21, 2, true],
            'day 22, reminded 6 days ago' => [22, 6, false],
            'day 22, reminded 7 days ago' => [22, 7, true],
            'day 60, reminded 7 days ago' => [60, 7, true],
            'already reminded today' => [3, 0, false],
            'an answer dated after today, after a time zone change' => [-1, 1, true],
        ];
    }

    #[DataProvider('tiers')]
    public function test_the_minimum_gap_depends_on_the_days_without_review(int $inactiveDays, ?int $daysSinceReminder, bool $allowed): void
    {
        $today = $this->day($inactiveDays);
        $lastReminder = $daysSinceReminder === null
            ? null
            : CarbonImmutable::parse($today)->subDays($daysSinceReminder)->toDateString();

        $this->assertSame($allowed, ReminderSpacing::allows($today, self::LAST_ANSWER, $lastReminder));
    }

    public function test_an_answer_brings_back_the_daily_rhythm(): void
    {
        $answeredAgain = $this->day(30);

        $this->assertFalse(ReminderSpacing::allows($this->day(30), self::LAST_ANSWER, $this->day(28)));
        $this->assertTrue(ReminderSpacing::allows($this->day(31), $answeredAgain, $this->day(28)));
        $this->assertTrue(ReminderSpacing::allows($this->day(32), $answeredAgain, $this->day(31)));
    }
}
