<?php

namespace Functional\Users\Notifications;

use Functional\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The password reset email (FR-005); the link opens the web page that sets a new password.
 * Queued so a slow or failing mail provider never blocks the "forgot password" request.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $resetUrl = config('app.frontend_url').'/reinitialiser-mot-de-passe?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('users::notifications.reset_password.subject'))
            ->greeting(__('users::notifications.greeting', ['name' => $notifiable->ownDisplayName()]))
            ->line(__('users::notifications.reset_password.intro'))
            ->action(__('users::notifications.reset_password.action'), $resetUrl)
            ->line(__('users::notifications.reset_password.expiry', ['minutes' => config('auth.passwords.users.expire')]))
            ->line(__('users::notifications.reset_password.ignore'))
            ->salutation(__('users::notifications.salutation'));
    }
}
