<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Reminders\Jobs\SendReviewReminder;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Notifications\ReviewReminderNotification;
use Functional\Users\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * FR-007, SC-002 — the command run every minute picks the accounts whose time has come.
 */
class DispatchDueRemindersCommandTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 19:00', 'Europe/Paris'));
    }

    private function settingDueAt(string $utc): ReminderSetting
    {
        return ReminderSetting::factory()->emailEnabled()->create(['next_reminder_at' => $utc]);
    }

    public function test_the_accounts_whose_time_has_come_are_queued_and_moved_to_the_next_day(): void
    {
        Queue::fake();
        $due = $this->settingDueAt('2026-09-29 17:00:00');
        $late = $this->settingDueAt('2026-09-29 16:58:00');
        $later = $this->settingDueAt('2026-09-29 17:30:00');

        $this->artisan('reminders:dispatch')->assertSuccessful();

        Queue::assertPushed(SendReviewReminder::class, 2);
        Queue::assertPushed(SendReviewReminder::class, fn (SendReviewReminder $job): bool => $job->reminderSettingId === $due->id);
        Queue::assertPushed(SendReviewReminder::class, fn (SendReviewReminder $job): bool => $job->reminderSettingId === $late->id);
        $this->assertSame('2026-09-30 17:00', $due->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-09-29 17:30', $later->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_an_account_without_any_channel_is_never_picked(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        ReminderSetting::query()->where('user_id', $user->id)->update(['next_reminder_at' => '2026-09-29 17:00:00']);

        $this->artisan('reminders:dispatch')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNull(ReminderSetting::query()->where('user_id', $user->id)->sole()->next_reminder_at);
    }

    public function test_a_given_time_replays_that_minute_and_sends_right_away(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-09-29 09:00', 'Europe/Paris'));
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $setting = ReminderSetting::factory()->emailEnabled()->for($user)->create(['next_reminder_at' => '2026-09-29 17:00:00']);
        $this->learn($user, $this->publishedSubjectWithQuestions(3));

        $this->artisan('reminders:dispatch', ['--now' => '2026-09-29 17:00'])->assertSuccessful();

        Notification::assertSentTo($setting, ReviewReminderNotification::class);
        $this->assertSame('2026-09-30 17:00', $setting->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-09-29 07:00', now()->utc()->format('Y-m-d H:i'));
    }

    public function test_the_command_runs_every_minute_on_one_server_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'reminders:dispatch'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
