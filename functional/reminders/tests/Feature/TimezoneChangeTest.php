<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edge case "changement de fuseau horaire" — the account's time zone is updated at login (001).
 */
class TimezoneChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_time_zone_moves_the_next_reminder_to_the_chosen_local_time_there(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00', 'Europe/Paris'));
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        ReminderSetting::factory()->emailEnabled()->for($user)->create(['next_reminder_at' => '2026-09-29 17:00:00']);

        $user->update(['timezone' => 'Asia/Tokyo']);

        $nextReminder = ReminderSetting::query()->where('user_id', $user->id)->sole()->next_reminder_at;
        $this->assertSame('2026-09-29 19:00', $nextReminder->setTimezone('Asia/Tokyo')->format('Y-m-d H:i'));
    }

    public function test_other_changes_of_the_account_leave_the_next_reminder_alone(): void
    {
        $user = User::factory()->create();
        ReminderSetting::factory()->emailEnabled()->for($user)->create(['next_reminder_at' => '2026-09-29 17:00:00']);

        $user->update(['display_name' => 'Camille Roux']);

        $nextReminder = ReminderSetting::query()->where('user_id', $user->id)->sole()->next_reminder_at;
        $this->assertSame('2026-09-29 17:00', $nextReminder->utc()->format('Y-m-d H:i'));
    }
}
