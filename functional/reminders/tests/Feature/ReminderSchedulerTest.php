<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Support\ReminderScheduler;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-007 — the next reminder of an account, kept on its settings for the minute-by-minute command.
 */
class ReminderSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00', 'Europe/Paris'));
    }

    public function test_no_active_channel_means_no_next_reminder(): void
    {
        $setting = ReminderSetting::factory()->create(['next_reminder_at' => now()]);

        app(ReminderScheduler::class)->refresh($setting);

        $this->assertNull($setting->fresh()->next_reminder_at);
    }

    public function test_the_email_channel_schedules_the_chosen_time_in_the_account_time_zone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Montreal']);
        $setting = ReminderSetting::factory()->emailEnabled()->for($user)->create(['send_time' => '08:00']);

        app(ReminderScheduler::class)->refresh($setting);

        $this->assertSame('2026-09-29 12:00', $setting->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_a_device_alone_is_an_active_channel(): void
    {
        $setting = ReminderSetting::factory()->create();
        PushSubscription::factory()->for($setting, 'subscribable')->create();

        app(ReminderScheduler::class)->refresh($setting);

        $this->assertSame('2026-09-29 17:00', $setting->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_a_day_that_already_had_its_reminder_moves_on_to_the_next_day(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        ReminderSend::factory()->create(['user_id' => $setting->user_id, 'local_date' => '2026-09-29']);

        app(ReminderScheduler::class)->refresh($setting);

        $this->assertSame('2026-09-30 17:00', $setting->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
    }
}
