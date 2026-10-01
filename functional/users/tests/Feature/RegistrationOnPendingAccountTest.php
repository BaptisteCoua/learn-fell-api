<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Functional\Users\Notifications\VerifyEmailNotification;
use Functional\Users\Support\EmailVerificationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature 005, user story 2 — FR-004 and FR-005: registering again on an account still waiting
 * for its confirmation sends it a new link, and nothing else.
 */
class RegistrationOnPendingAccountTest extends TestCase
{
    use RefreshDatabase;

    private function registerAgain(): TestResponse
    {
        return $this->postJson('/api/register', [
            'display_name' => 'Dominique',
            'email' => 'camille@exemple.fr',
            'password' => 'autremotdepasse',
            'password_confirmation' => 'autremotdepasse',
            'timezone' => 'Asia/Tokyo',
        ])->assertCreated();
    }

    /**
     * The API call the web confirmation page makes from a link.
     */
    private function apiPathOf(string $webLink): string
    {
        parse_str((string) parse_url($webLink, PHP_URL_QUERY), $query);

        return "/api/email/verify/{$query['id']}/{$query['hash']}?".http_build_query([
            'expires' => $query['expires'],
            'nonce' => $query['nonce'],
            'signature' => $query['signature'],
        ]);
    }

    private function pendingAccount(): User
    {
        return User::factory()->unverified()->create([
            'email' => 'camille@exemple.fr',
            'display_name' => 'Camille',
            'timezone' => 'Europe/Paris',
            'created_at' => now()->subDays(2),
        ]);
    }

    public function test_a_new_link_replaces_the_previous_one_and_nothing_else_changes(): void
    {
        $account = $this->pendingAccount();
        $previousLink = $this->apiPathOf(app(EmailVerificationLink::class)->issue($account));
        $createdAt = $account->fresh()->created_at;
        Notification::fake();

        $this->registerAgain();

        $newLink = null;
        Notification::assertSentTo($account, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($account, &$newLink): bool {
            $newLink = $this->apiPathOf($notification->toMail($account)->actionUrl);

            return true;
        });
        $this->getJson($previousLink)->assertForbidden()->assertJson(['code' => 'link_expired']);
        $this->getJson((string) $newLink)->assertNoContent();

        $account->refresh();
        $this->assertNotNull($account->email_verified_at);
        $this->assertSame('Camille', $account->display_name);
        $this->assertSame('Europe/Paris', $account->timezone);
        $this->assertTrue(Hash::check('password', $account->password));
        $this->assertEquals($createdAt, $account->created_at);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_one_link_a_minute_at_most(): void
    {
        $account = $this->pendingAccount();
        Notification::fake();

        $this->registerAgain();
        $this->registerAgain();
        Notification::assertSentToTimes($account, VerifyEmailNotification::class, 1);

        $this->travel(61)->seconds();
        $this->registerAgain();
        Notification::assertSentToTimes($account, VerifyEmailNotification::class, 2);
    }

    public function test_a_new_link_does_not_postpone_the_removal_of_an_unconfirmed_account(): void
    {
        $account = User::factory()->unverified()->create(['email' => 'camille@exemple.fr', 'created_at' => now()->subDays(8)]);
        $this->travel(-1)->days();
        $this->registerAgain();
        $this->travelBack();

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($account);
    }
}
