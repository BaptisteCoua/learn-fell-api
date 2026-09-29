<?php

namespace Functional\Reminders\Notifications;

use Functional\Reminders\Domain\DueReminder;
use Functional\Reminders\Models\ReminderSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The reminder of the day (FR-009, FR-011): the cards due today and a link to their session,
 * in French and nothing personal beyond the display name. A notification can show on a
 * locked screen, so it carries the count alone (research R9).
 */
class ReviewReminderNotification extends Notification
{
    public const PUSH_TAG = 'review-reminder';

    private const PUSH_TIME_TO_LIVE_SECONDS = 4 * 3600;

    /**
     * @param  list<string>  $channels  the channels of this sending, each sent on its own
     */
    public function __construct(
        public readonly DueReminder $reminder,
        private readonly array $channels = ['mail'],
    ) {}

    /**
     * @return list<string>
     */
    public function via(ReminderSetting $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(ReminderSetting $notifiable): MailMessage
    {
        $count = $this->reminder->cardsCount;

        return (new MailMessage)
            ->subject(trans_choice('reminders::notifications.review_reminder.subject', $count, ['count' => $count]))
            ->greeting(__('users::notifications.greeting', ['name' => $notifiable->user->display_name]))
            ->line(trans_choice('reminders::notifications.review_reminder.intro', $count, ['count' => $count]))
            ->action(__('reminders::notifications.review_reminder.action'), $this->sessionUrl())
            ->salutation(__('users::notifications.salutation'));
    }

    public function toWebPush(ReminderSetting $notifiable): WebPushMessage
    {
        $count = $this->reminder->cardsCount;

        return (new WebPushMessage)
            ->title(__('reminders::notifications.review_reminder.push_title'))
            ->body(trans_choice('reminders::notifications.review_reminder.push_body', $count, ['count' => $count]))
            ->icon('/icons/icon-192.png')
            ->badge('/favicon-48.png')
            ->tag(self::PUSH_TAG)
            ->data(['url' => $this->sessionUrl()])
            ->options(['TTL' => self::PUSH_TIME_TO_LIVE_SECONDS, 'urgency' => 'normal']);
    }

    /**
     * The review session of 001 on the subjects with due cards; the web app sends a visitor
     * to the login page and back (FR-010).
     */
    private function sessionUrl(): string
    {
        return config('app.frontend_url').'/revisions/seance?sujets='.implode(',', $this->reminder->subjectIds);
    }
}
