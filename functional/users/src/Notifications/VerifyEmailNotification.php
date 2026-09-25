<?php

namespace Functional\Users\Notifications;

use Functional\Users\Models\User;
use Functional\Users\Support\EmailVerificationLink;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The confirmation email sent at registration and on request (FR-002).
 */
class VerifyEmailNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('users::notifications.verify_email.subject'))
            ->greeting(__('users::notifications.greeting', ['name' => $notifiable->display_name]))
            ->line(__('users::notifications.verify_email.intro'))
            ->action(__('users::notifications.verify_email.action'), app(EmailVerificationLink::class)->issue($notifiable))
            ->line(__('users::notifications.verify_email.expiry'))
            ->line(__('users::notifications.verify_email.ignore'))
            ->salutation(__('users::notifications.salutation'));
    }
}
