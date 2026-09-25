<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Functional\Users\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * FR-001, FR-002 — scenarios 1, 2 and 6 of user story 2.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return [
            'display_name' => 'Camille Roux',
            'email' => 'Camille@Exemple.fr',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
            'timezone' => 'America/Montreal',
            ...$overrides,
        ];
    }

    public function test_a_visitor_registers_an_inactive_account_without_being_logged_in(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', $this->registration());

        $response->assertCreated();
        $user = User::query()->where('email', 'camille@exemple.fr')->sole();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('America/Montreal', $user->timezone);
        $this->assertGuest('web');
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_the_confirmation_email_links_to_the_web_confirmation_page(): void
    {
        Notification::fake();

        $this->postJson('/api/register', $this->registration())->assertCreated();

        $user = User::query()->sole();
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_starts_with($mail->actionUrl, config('app.frontend_url').'/inscription/confirmation?')
                && $mail->subject === 'Confirmez votre adresse email';
        });
    }

    public function test_two_different_passwords_are_refused_under_the_confirmation(): void
    {
        $response = $this->postJson('/api/register', $this->registration(['password_confirmation' => 'autrechose']));

        $response->assertUnprocessable()->assertJsonValidationErrors(['password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_password_needs_eight_characters(): void
    {
        $response = $this->postJson('/api/register', $this->registration([
            'password' => 'court',
            'password_confirmation' => 'court',
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_an_address_of_an_active_account_invites_to_log_in(): void
    {
        User::factory()->create(['email' => 'camille@exemple.fr']);

        $response = $this->postJson('/api/register', $this->registration());

        $response->assertUnprocessable()->assertJson(['code' => 'email_taken']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_address_waiting_for_confirmation_offers_a_new_link(): void
    {
        User::factory()->unverified()->create(['email' => 'camille@exemple.fr']);

        $response = $this->postJson('/api/register', $this->registration());

        $response->assertUnprocessable()->assertJson(['code' => 'email_pending_verification']);
    }
}
