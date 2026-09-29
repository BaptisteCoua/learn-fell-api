<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Reminders\Jobs\SendReviewReminder;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Notifications\ReviewReminderNotification;
use Functional\Reminders\Tests\Concerns\FakesWebPush;
use Functional\Reminders\Tests\Concerns\ManagesReminders;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * FR-007, FR-008, FR-009, FR-019, FR-020, SC-003 — the reminder of the day.
 */
class SendReviewReminderTest extends TestCase
{
    use FakesWebPush, ManagesReminders, RefreshDatabase, ReviewsCards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 19:00', 'Europe/Paris'));
        $this->configureVapid();
        $this->fakePushService();
        Notification::fake();
    }

    /**
     * An account with 12 cards due today on 2 subjects.
     *
     * @return array{ReminderSetting, list<int>}
     */
    private function learnerWithTwelveDueCards(bool $email = true): array
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $setting = $email
            ? ReminderSetting::factory()->emailEnabled()->for($user)->create()
            : $this->settingOf($user);
        $verbs = $this->publishedSubjectWithQuestions(7);
        $dates = $this->publishedSubjectWithQuestions(5);
        $this->learn($user, $verbs);
        $this->learn($user, $dates);

        return [$setting->fresh(), [$verbs->id, $dates->id]];
    }

    private function send(ReminderSetting $setting): void
    {
        SendReviewReminder::dispatchSync($setting->id);
    }

    public function test_the_email_announces_the_due_cards_of_the_day(): void
    {
        [$setting, $subjectIds] = $this->learnerWithTwelveDueCards();

        $this->send($setting);

        Notification::assertSentTo($setting, ReviewReminderNotification::class, function (ReviewReminderNotification $notification) use ($subjectIds): bool {
            return $notification->reminder->cardsCount === 12 && $notification->reminder->subjectIds === $subjectIds;
        });
        $send = ReminderSend::query()->sole();
        $this->assertSame('2026-09-29', $send->local_date->toDateString());
        $this->assertSame(12, $send->cards_count);
        $this->assertSame($subjectIds, $send->subject_ids);
        $this->assertSame(['mail'], $send->channels);
    }

    public function test_each_device_receives_the_notification(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards(email: false);
        $phone = $this->deviceOf($setting, ['endpoint' => 'https://push.example.test/phone']);
        $computer = $this->deviceOf($setting, ['endpoint' => 'https://push.example.test/computer']);

        $this->send($setting);

        Http::assertSent(fn ($request): bool => (string) $request->url() === $phone->endpoint);
        Http::assertSent(fn ($request): bool => (string) $request->url() === $computer->endpoint);
        Notification::assertNothingSent();
        $this->assertSame(['webpush'], ReminderSend::query()->sole()->channels);
    }

    public function test_both_channels_receive_the_same_reminder_once(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        $this->deviceOf($setting);

        $this->send($setting);

        Notification::assertSentToTimes($setting, ReviewReminderNotification::class, 1);
        Http::assertSentCount(1);
        $this->assertSame(['mail', 'webpush'], ReminderSend::query()->sole()->channels);
    }

    public function test_the_email_does_not_depend_on_the_push_configuration(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        config(['webpush.vapid.private_key' => 'not-a-key']);

        $this->send($setting);

        Notification::assertSentTo($setting, ReviewReminderNotification::class);
        $this->assertSame(['mail'], ReminderSend::query()->sole()->channels);
    }

    public function test_a_second_job_the_same_day_sends_nothing(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();

        $this->send($setting);
        $this->send($setting);

        Notification::assertSentToTimes($setting, ReviewReminderNotification::class, 1);
        $this->assertDatabaseCount('reminder_sends', 1);
    }

    public function test_a_later_time_chosen_the_same_day_does_not_send_a_second_reminder(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        $this->send($setting);

        $this->updateSettings($setting->user, $setting->id, ['send_time' => '22:00'])->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-09-29 22:00', 'Europe/Paris'));
        $this->artisan('reminders:dispatch')->assertSuccessful();

        Notification::assertSentToTimes($setting, ReviewReminderNotification::class, 1);
        $this->assertSame('2026-09-30 20:00', $setting->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_nothing_is_sent_once_every_card_of_the_day_is_reviewed(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        CardProgress::query()->update(['next_review_on' => '2026-09-30']);

        $this->send($setting);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('reminder_sends', 0);
    }

    public function test_an_account_that_learns_nothing_receives_nothing(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        Learning::query()->each(fn (Learning $learning) => $learning->delete());

        $this->send($setting);

        Notification::assertNothingSent();
    }

    public function test_the_count_is_the_one_at_sending_time(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        CardProgress::query()->limit(5)->update(['next_review_on' => '2026-09-30']);

        $this->send($setting);

        $this->assertSame(7, ReminderSend::query()->sole()->cards_count);
    }

    public function test_a_retry_after_a_failure_only_serves_the_channels_still_missing(): void
    {
        [$setting] = $this->learnerWithTwelveDueCards();
        ReminderSend::factory()->create([
            'user_id' => $setting->user_id,
            'local_date' => '2026-09-29',
            'cards_count' => 12,
            'channels' => ['mail'],
        ]);
        $this->deviceOf($setting);
        $retry = (new SendReviewReminder($setting->id))->withFakeQueueInteractions();
        $retry->job->attempts = 2;

        app()->call([$retry, 'handle']);

        Notification::assertNothingSent();
        Http::assertSentCount(1);
        $this->assertSame(['mail', 'webpush'], ReminderSend::query()->sole()->channels);
    }
}
