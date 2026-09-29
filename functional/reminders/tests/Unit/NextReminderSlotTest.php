<?php

namespace Functional\Reminders\Tests\Unit;

use Carbon\CarbonImmutable;
use Functional\Reminders\Domain\NextReminderSlot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FR-007 and the edge cases of the spec — the next reminder in the account's local time.
 */
class NextReminderSlotTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string|null, string}>
     */
    public static function slots(): array
    {
        return [
            'the time is still to come today' => ['19:00', 'Europe/Paris', '2026-09-29 10:00', null, '2026-09-29 17:00'],
            'the time has passed today' => ['19:00', 'Europe/Paris', '2026-09-29 20:00', null, '2026-09-30 17:00'],
            'the time is right now' => ['19:00', 'Europe/Paris', '2026-09-29 19:00', null, '2026-09-30 17:00'],
            'today already had its reminder' => ['19:00', 'Europe/Paris', '2026-09-29 10:00', '2026-09-29', '2026-09-30 17:00'],
            'yesterday had its reminder' => ['08:00', 'Europe/Paris', '2026-09-29 07:00', '2026-09-28', '2026-09-29 06:00'],
            'a local time skipped by summer time comes an hour later' => ['02:30', 'Europe/Paris', '2026-03-28 12:00', null, '2026-03-29 01:30'],
            'a local time repeated by winter time takes the first one' => ['02:30', 'Europe/Paris', '2026-10-24 12:00', null, '2026-10-25 00:30'],
            'a local time just after winter time is not repeated' => ['05:00', 'Europe/Paris', '2026-10-24 12:00', null, '2026-10-25 04:00'],
        ];
    }

    #[DataProvider('slots')]
    public function test_the_next_slot_is_the_chosen_local_time_in_utc(string $sendTime, string $timezone, string $localNow, ?string $lastLocalDate, string $expectedUtc): void
    {
        $now = CarbonImmutable::parse($localNow, $timezone);

        $slot = NextReminderSlot::next($sendTime, $timezone, $now, $lastLocalDate);

        $this->assertSame('UTC', $slot->getTimezone()->getName());
        $this->assertSame($expectedUtc, $slot->format('Y-m-d H:i'));
    }

    public function test_moving_east_the_reminder_follows_the_new_time_zone_the_next_local_day(): void
    {
        $sentInParisOn = '2026-09-29';
        $now = CarbonImmutable::parse('2026-09-30 02:00', 'Asia/Tokyo');

        $slot = NextReminderSlot::next('19:00', 'Asia/Tokyo', $now, $sentInParisOn);

        $this->assertSame('2026-09-30 19:00', $slot->setTimezone('Asia/Tokyo')->format('Y-m-d H:i'));
    }

    public function test_moving_west_never_sends_twice_on_the_same_local_date(): void
    {
        $sentInTokyoOn = '2026-09-29';
        $now = CarbonImmutable::parse('2026-09-29 07:00', 'America/Montreal');

        $slot = NextReminderSlot::next('19:00', 'America/Montreal', $now, $sentInTokyoOn);

        $this->assertSame('2026-09-30 19:00', $slot->setTimezone('America/Montreal')->format('Y-m-d H:i'));
    }
}
