<?php

namespace Functional\Users\Notifications;

use Functional\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Feature 004, FR-012 — confirms a deletion request: the erasure date, and that logging in
 * before then cancels it. No link cancels or confirms anything without logging in.
 */
class AccountDeletionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $eraseOn = $notifiable->eraseOn()->locale('fr')->translatedFormat('j F Y');

        return (new MailMessage)
            ->subject(__('users::notifications.account_deletion.subject', ['date' => $eraseOn]))
            ->greeting(__('users::notifications.greeting', ['name' => $notifiable->ownDisplayName()]))
            ->line(__('users::notifications.account_deletion.intro', ['date' => $eraseOn]))
            ->line(__($notifiable->keeps_published_subjects === true
                ? 'users::notifications.account_deletion.subjects_kept'
                : 'users::notifications.account_deletion.everything_erased'))
            ->line(__('users::notifications.account_deletion.moderation_kept'))
            ->line(__('users::notifications.account_deletion.cancel', ['date' => $eraseOn]))
            ->action(__('users::notifications.account_deletion.action'), config('app.frontend_url').'/connexion')
            ->salutation(__('users::notifications.salutation'));
    }
}
