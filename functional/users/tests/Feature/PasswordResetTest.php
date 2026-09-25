<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Functional\Users\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * FR-005, FR-006 — scenario 9 of user story 2.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_answer_is_the_same_whether_the_address_has_an_account_or_not(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'camille@exemple.fr']);

        $known = $this->postJson('/api/forgot-password', ['email' => 'camille@exemple.fr']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'personne@exemple.fr']);

        $known->assertAccepted();
        $unknown->assertAccepted();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            return str_starts_with($notification->toMail($user)->actionUrl, config('app.frontend_url').'/reinitialiser-mot-de-passe?token=');
        });
    }

    public function test_a_new_password_typed_twice_replaces_the_old_one_and_closes_the_other_sessions(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr']);
        DB::table('sessions')->insert([
            'id' => 'autre-appareil',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'camille@exemple.fr',
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'nouveaumotdepasse',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('nouveaumotdepasse', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr']);

        $response = $this->postJson('/api/reset-password', [
            'token' => Password::broker()->createToken($user),
            'email' => 'camille@exemple.fr',
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'autrechose',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_the_link_works_once_and_for_60_minutes(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr']);
        $reset = fn (string $token) => $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'camille@exemple.fr',
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'nouveaumotdepasse',
        ]);

        $usedToken = Password::broker()->createToken($user);
        $reset($usedToken)->assertNoContent();
        $reset($usedToken)->assertUnprocessable()->assertJson(['code' => 'link_expired']);

        $expiredToken = Password::broker()->createToken($user->fresh());
        $this->travel(61)->minutes();
        $reset($expiredToken)->assertUnprocessable()->assertJson(['code' => 'link_expired']);
    }
}
