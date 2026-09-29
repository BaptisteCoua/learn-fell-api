<?php

namespace Functional\Reminders\Jobs;

use Functional\Reminders\Domain\DueReminder;
use Functional\Reminders\Domain\ReminderEligibility;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Notifications\ReviewReminderNotification;
use Functional\Reminders\Support\RecordsEmailBounce;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\WebPushChannel;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Sends the reminder of the day to one account (research R4), if it still has cards due.
 *
 * The day is logged before anything is sent: the unique (account, local date) index makes a
 * second job of the same day stop there (FR-007). Each channel is sent on its own, so a
 * failure of one does not stop the other; a retry only serves the channels still missing.
 */
class SendReviewReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const MAIL = 'mail';

    public const WEBPUSH = 'webpush';

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $reminderSettingId) {}

    public function uniqueId(): string
    {
        return (string) $this->reminderSettingId;
    }

    public function handle(ReminderEligibility $eligibility, RecordsEmailBounce $bounces): void
    {
        $setting = ReminderSetting::query()->with('user')->find($this->reminderSettingId);

        if ($setting === null) {
            return;
        }

        $send = $this->todaysSend($setting, $eligibility);

        if ($send === null) {
            return;
        }

        $reminder = new DueReminder($send->cards_count, $send->subject_ids);

        if ($setting->email_enabled && ! in_array(self::MAIL, $send->channels, true)) {
            $this->sendEmail($setting, $reminder, $send, $bounces);
        }

        if (! in_array(self::WEBPUSH, $send->channels, true) && $this->pushReached($setting, $reminder)) {
            $this->markServed($send, self::WEBPUSH);
        }
    }

    /**
     * The log of today: written now on a first attempt, reread on a retry.
     */
    private function todaysSend(ReminderSetting $setting, ReminderEligibility $eligibility): ?ReminderSend
    {
        $localDate = now($setting->user->timezone)->toDateString();

        if ($this->attempts() > 1) {
            $send = ReminderSend::query()->where('user_id', $setting->user_id)->whereDate('local_date', $localDate)->first();

            if ($send !== null) {
                return $send;
            }
        }

        $reminder = $eligibility->for($setting);

        if ($reminder === null) {
            return null;
        }

        // Its own transaction: PostgreSQL aborts the enclosing one on a violated unique index.
        try {
            return DB::transaction(fn (): ReminderSend => ReminderSend::query()->create([
                'user_id' => $setting->user_id,
                'local_date' => $localDate,
                'cards_count' => $reminder->cardsCount,
                'subject_ids' => $reminder->subjectIds,
                'channels' => [],
                'sent_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * A definite refusal (SMTP 5xx) is counted and not tried again; any other failure is
     * thrown for the queue to retry (research R8).
     */
    private function sendEmail(ReminderSetting $setting, DueReminder $reminder, ReminderSend $send, RecordsEmailBounce $bounces): void
    {
        try {
            $setting->notifyNow(new ReviewReminderNotification($reminder, [self::MAIL]));
        } catch (TransportExceptionInterface $failure) {
            if ($failure->getCode() < 500 || $failure->getCode() > 599) {
                throw $failure;
            }

            $bounces->recordRefusal($setting);

            return;
        }

        $bounces->recordDelivery($setting);
        $this->markServed($send, self::MAIL);
    }

    /**
     * Served when at least one device accepted it; an expired device is removed by the channel.
     * The channel is only built for an account with devices, so that the push configuration
     * never holds the email back.
     */
    private function pushReached(ReminderSetting $setting, DueReminder $reminder): bool
    {
        if (! $setting->pushSubscriptions()->exists()) {
            return false;
        }

        $reports = app(WebPushChannel::class)->send($setting, new ReviewReminderNotification($reminder, [WebPushChannel::class]));

        return collect($reports)->contains(fn (MessageSentReport $report): bool => $report->isSuccess());
    }

    private function markServed(ReminderSend $send, string $channel): void
    {
        $send->channels = [...$send->channels, $channel];
        $send->save();
    }
}
