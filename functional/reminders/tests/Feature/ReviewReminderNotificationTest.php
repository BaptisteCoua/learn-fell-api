<?php

namespace Functional\Reminders\Tests\Feature;

use Functional\Reminders\Domain\DueReminder;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Notifications\ReviewReminderNotification;
use Functional\Reminders\Support\UnsubscribeLink;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * FR-009, FR-011 — what a reminder says, by email and by notification.
 */
class ReviewReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function settingOf(string $displayName): ReminderSetting
    {
        $user = User::factory()->create(['display_name' => $displayName, 'email' => 'camille@exemple.fr']);

        return ReminderSetting::query()->where('user_id', $user->id)->sole();
    }

    public function test_the_email_announces_the_cards_and_opens_the_session_on_their_subjects(): void
    {
        $setting = $this->settingOf('Camille Roux');

        $mail = (new ReviewReminderNotification(new DueReminder(12, [1, 2])))->toMail($setting);

        $this->assertSame('12 cartes à réviser aujourd’hui', $mail->subject);
        $this->assertSame('Bonjour Camille Roux,', $mail->greeting);
        $this->assertContains('Vous avez 12 cartes à réviser aujourd’hui. Quelques minutes suffisent.', $mail->introLines);
        $this->assertSame('Réviser maintenant', $mail->actionText);
        $this->assertSame('http://localhost:3000/revisions/seance?sujets=1,2', $mail->actionUrl);
    }

    public function test_the_email_can_be_unsubscribed_in_one_click_from_its_body_and_its_mailbox(): void
    {
        $setting = $this->settingOf('Camille Roux');
        $links = app(UnsubscribeLink::class);

        $mail = (new ReviewReminderNotification(new DueReminder(12, [1, 2])))->toMail($setting);
        $email = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        $this->assertSame('<'.$links->forMailbox($setting).'>', $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());

        $html = (string) $mail->render();
        $this->assertStringContainsString('Vous recevez cet email parce que vous avez activé les rappels de révision.', $html);
        $this->assertStringContainsString('Ne plus recevoir ces rappels', $html);
        $this->assertStringContainsString(e($links->forWebPage($setting)), $html);
    }

    public function test_one_card_is_announced_in_the_singular(): void
    {
        $mail = (new ReviewReminderNotification(new DueReminder(1, [4])))->toMail($this->settingOf('Inès'));

        $this->assertSame('1 carte à réviser aujourd’hui', $mail->subject);
        $this->assertContains('Vous avez 1 carte à réviser aujourd’hui. Quelques minutes suffisent.', $mail->introLines);
    }

    public function test_the_email_goes_to_the_account_address(): void
    {
        $this->assertSame('camille@exemple.fr', $this->settingOf('Camille')->routeNotificationForMail());
    }

    public function test_the_notification_shows_the_count_only_and_opens_the_session(): void
    {
        $setting = $this->settingOf('Camille Roux');

        $message = (new ReviewReminderNotification(new DueReminder(12, [1, 2])))->toWebPush($setting);

        $this->assertEquals([
            'title' => 'CINQ',
            'body' => '12 cartes à réviser aujourd’hui',
            'icon' => '/icons/icon-192.png',
            'badge' => '/favicon-48.png',
            'tag' => 'review-reminder',
            'data' => ['url' => 'http://localhost:3000/revisions/seance?sujets=1,2'],
        ], $message->toArray());
        $this->assertSame(['TTL' => 14400, 'urgency' => 'normal'], $message->getOptions());
        $this->assertStringNotContainsString('Camille', json_encode($message->toArray()));
    }
}
