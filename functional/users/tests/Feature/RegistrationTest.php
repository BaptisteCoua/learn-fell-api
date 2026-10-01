<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Functional\Users\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * FR-001, FR-002 — scenarios 1 and 2 of user story 2; feature 005 replaces scenario 6.
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

    /**
     * Feature 005, FR-001 and SC-001 — the four kinds of address get the very same answer.
     */
    public function test_a_valid_registration_gets_the_same_answer_whatever_the_address(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'confirme@exemple.fr']);
        User::factory()->pendingDeletion()->create(['email' => 'supprime@exemple.fr']);
        User::factory()->unverified()->create(['email' => 'attente@exemple.fr']);

        $answers = collect(['libre@exemple.fr', 'confirme@exemple.fr', 'supprime@exemple.fr', 'attente@exemple.fr'])
            ->map(function (string $email): array {
                $response = $this->postJson('/api/register', $this->registration(['email' => $email]));

                return [$response->status(), $response->json()];
            });

        $this->assertSame(1, $answers->unique(fn (array $answer): string => json_encode($answer))->count());
        $this->assertSame([201, ['message' => 'Si cette adresse peut être utilisée, un lien de confirmation vient d’y être envoyé.']], $answers->first());
        $this->assertGuest('web');
    }

    public function test_an_address_with_other_capitals_is_the_same_address(): void
    {
        Notification::fake();
        $confirmed = User::factory()->create(['email' => 'camille@exemple.fr', 'display_name' => 'Camille']);

        $this->postJson('/api/register', $this->registration(['email' => 'CAMILLE@Exemple.FR']))->assertCreated();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('Camille', $confirmed->fresh()->display_name);
        Notification::assertNothingSent();
    }

    /**
     * Feature 005, FR-007 — input errors do not depend on whether the address has an account.
     */
    public function test_input_errors_are_the_same_for_a_free_and_a_taken_address(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'prise@exemple.fr']);
        $invalid = ['display_name' => 'C', 'password' => 'court', 'password_confirmation' => 'court'];

        $free = $this->postJson('/api/register', $this->registration([...$invalid, 'email' => 'libre@exemple.fr']));
        $taken = $this->postJson('/api/register', $this->registration([...$invalid, 'email' => 'prise@exemple.fr']));

        $free->assertUnprocessable()->assertJsonValidationErrors(['display_name', 'password']);
        $this->assertSame($free->json(), $taken->json());
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }
}
