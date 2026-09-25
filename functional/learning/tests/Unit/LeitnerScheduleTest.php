<?php

namespace Functional\Learning\Tests\Unit;

use Carbon\CarbonImmutable;
use Functional\Learning\Domain\LeitnerSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FR-046, SC-010 — every transition of the data model's table.
 */
class LeitnerScheduleTest extends TestCase
{
    /**
     * @return array<string, array{int, bool, int, int}>
     */
    public static function transitions(): array
    {
        return [
            'box 1, known' => [1, true, 2, 2],
            'box 2, known' => [2, true, 3, 4],
            'box 3, known' => [3, true, 4, 8],
            'box 4, known' => [4, true, 5, 16],
            'box 5, known' => [5, true, 5, 16],
            'box 1, missed' => [1, false, 1, 1],
            'box 2, missed' => [2, false, 1, 1],
            'box 3, missed' => [3, false, 1, 1],
            'box 4, missed' => [4, false, 1, 1],
            'box 5, missed' => [5, false, 1, 1],
        ];
    }

    #[DataProvider('transitions')]
    public function test_each_answer_moves_the_card_and_sets_its_next_review(int $box, bool $known, int $expectedBox, int $expectedDays): void
    {
        $today = CarbonImmutable::parse('2026-09-25', 'Europe/Paris');

        $arrivalBox = LeitnerSchedule::arrivalBox($box, $known);

        $this->assertSame($expectedBox, $arrivalBox);
        $this->assertSame(
            $today->addDays($expectedDays)->toDateString(),
            LeitnerSchedule::nextReviewOn($arrivalBox, $today)->toDateString(),
        );
    }

    public function test_today_is_the_learners_own_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 22:30', 'UTC'));

        $this->assertSame('2026-09-25', LeitnerSchedule::todayFor('Europe/London')->toDateString());
        $this->assertSame('2026-09-26', LeitnerSchedule::todayFor('Europe/Paris')->toDateString());
        $this->assertSame('2026-09-25', LeitnerSchedule::todayFor('America/Montreal')->toDateString());
        $this->assertSame('2026-09-26', LeitnerSchedule::todayFor('Asia/Tokyo')->toDateString());

        CarbonImmutable::setTestNow();
    }
}
