<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Reminders\Enums\EmailDisabledReason;
use Functional\Reminders\Jobs\SendReviewReminder;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Tests\Concerns\FakesWebPush;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

/**
 * FR-017, FR-018 — a device that can no longer receive and an address that keeps refusing.
 */
class DeliveryFailuresTest extends TestCase
{
    use FakesWebPush, RefreshDatabase, ReviewsCards;

    private ReminderSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 19:00', 'Europe/Paris'));
        $this->configureVapid();
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $this->setting = ReminderSetting::factory()->emailEnabled()->for($user)->create();
        $this->learn($user, $this->publishedSubjectWithQuestions(3));
    }

    /**
     * Every email is refused by the mail server with this exception.
     */
    private function mailServerRefusing(TransportException $refusal): void
    {
        Mail::extend('refusing', fn () => new class($refusal) implements TransportInterface
        {
            public function __construct(private readonly TransportException $refusal) {}

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw $this->refusal;
            }

            public function __toString(): string
            {
                return 'refusing://';
            }
        });
        config(['mail.mailers.refusing' => ['transport' => 'refusing'], 'mail.default' => 'refusing']);
    }

    private function sendOnDay(int $day): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 19:00', 'Europe/Paris')->addDays($day));
        SendReviewReminder::dispatchSync($this->setting->id);
    }

    public function test_a_delivered_notification_records_the_delivery_of_its_device(): void
    {
        $this->fakePushService();
        $device = $this->deviceOf($this->setting);

        SendReviewReminder::dispatchSync($this->setting->id);

        $this->assertEquals(now(), $device->fresh()->last_delivered_at);
    }

    public function test_a_device_that_can_no_longer_receive_is_removed_and_the_email_still_goes(): void
    {
        $gone = $this->deviceOf($this->setting, ['endpoint' => 'https://push.example.test/gone']);
        $kept = $this->deviceOf($this->setting, ['endpoint' => 'https://push.example.test/kept']);
        $this->fakePushService([$gone->endpoint => 410]);

        SendReviewReminder::dispatchSync($this->setting->id);

        $this->assertModelMissing($gone);
        $this->assertModelExists($kept);
        $this->assertSame(['mail', 'webpush'], ReminderSend::query()->sole()->channels);
    }

    public function test_an_unknown_device_is_removed_too(): void
    {
        $unknown = $this->deviceOf($this->setting, ['endpoint' => 'https://push.example.test/unknown']);
        $this->fakePushService([$unknown->endpoint => 404]);

        SendReviewReminder::dispatchSync($this->setting->id);

        $this->assertModelMissing($unknown);
        $this->assertSame(['mail'], ReminderSend::query()->sole()->channels);
    }

    public function test_three_refusals_in_a_row_turn_the_emails_off(): void
    {
        $this->mailServerRefusing(new UnexpectedResponseException('550 5.1.1 Mailbox unavailable', 550));

        $this->sendOnDay(0);
        $this->sendOnDay(1);
        $this->assertTrue($this->setting->fresh()->email_enabled);
        $this->sendOnDay(2);

        $this->setting->refresh();
        $this->assertFalse($this->setting->email_enabled);
        $this->assertSame(EmailDisabledReason::Bounced, $this->setting->email_disabled_reason);
        $this->assertSame(3, $this->setting->email_bounce_count);
        $this->assertNull($this->setting->next_reminder_at);
    }

    public function test_an_accepted_email_resets_the_count_of_refusals(): void
    {
        $this->setting->update(['email_bounce_count' => 2]);

        SendReviewReminder::dispatchSync($this->setting->id);

        $this->assertSame(0, $this->setting->fresh()->email_bounce_count);
    }

    public function test_a_temporary_failure_is_left_to_the_retries_of_the_queue(): void
    {
        $this->mailServerRefusing(new UnexpectedResponseException('451 4.3.0 Try again later', 451));

        $this->expectException(TransportException::class);

        try {
            SendReviewReminder::dispatchSync($this->setting->id);
        } finally {
            $this->assertSame(0, $this->setting->fresh()->email_bounce_count);
            $this->assertTrue($this->setting->fresh()->email_enabled);
        }
    }
}
