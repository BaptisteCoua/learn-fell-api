<?php

namespace Functional\Users\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Users\Models\User;
use Functional\Users\Notifications\AccountDeletionRequestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Feature 004, FR-012 — the email that confirms the request and says how to cancel it.
 */
class AccountDeletionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_gives_the_erasure_date_and_how_to_cancel(): void
    {
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Europe/Paris'));
        $user = User::factory()->create(['display_name' => 'Inès Martin']);

        $this->actingAs($user, 'web')->postJson('/api/account/deletion', ['password' => 'password'])->assertOk();

        Notification::assertSentTo($user, AccountDeletionRequestedNotification::class, function (AccountDeletionRequestedNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user->fresh());
            $text = implode("\n", [...$mail->introLines, ...$mail->outroLines]);

            return $mail->subject === 'Votre compte CINQ sera supprimé le 31 octobre 2026'
                && $mail->greeting === 'Bonjour Inès Martin,'
                && str_contains($text, 'reconnecter')
                && $mail->actionUrl === config('app.frontend_url').'/connexion';
        });
    }

    public function test_the_email_is_queued_so_a_failed_delivery_does_not_undo_the_request(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AccountDeletionRequestedNotification);
    }
}
