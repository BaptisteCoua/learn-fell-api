<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Functional\Users\Notifications\VerifyEmailNotification;
use Functional\Users\Support\EmailVerificationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * FR-002 — scenarios 3 and 5 of user story 2.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The API call the web confirmation page makes from the link it received.
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

    private function linkFor(User $user): string
    {
        return $this->apiPathOf(app(EmailVerificationLink::class)->issue($user));
    }

    public function test_the_link_activates_the_account_and_logs_it_in(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->getJson($this->linkFor($user));

        $response->assertNoContent();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_the_link_works_only_once(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);
        $this->getJson($link)->assertNoContent();
        $this->app['auth']->guard('web')->logout();

        $this->getJson($link)->assertForbidden()->assertJson(['code' => 'link_expired']);
    }

    public function test_the_link_expires_after_24_hours(): void
    {
        $user = User::factory()->unverified()->create();
        $link = $this->linkFor($user);

        $this->travel(24 * 60 + 1)->minutes();

        $this->getJson($link)->assertForbidden()->assertJson(['code' => 'link_expired']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $link = str_replace("/verify/{$user->id}/", "/verify/{$other->id}/", $this->linkFor($user));

        $this->getJson($link)->assertForbidden()->assertJson(['code' => 'link_expired']);
    }

    public function test_sending_a_new_link_replaces_the_previous_one(): void
    {
        $user = User::factory()->unverified()->create();
        $firstLink = $this->linkFor($user);
        $secondLink = $this->linkFor($user);

        $this->getJson($firstLink)->assertForbidden();
        $this->getJson($secondLink)->assertNoContent();
    }

    public function test_a_new_link_can_be_requested_without_revealing_the_address(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'ines@exemple.fr']);

        $known = $this->postJson('/api/email/verification-notification', ['email' => 'Ines@Exemple.fr']);
        $unknown = $this->postJson('/api/email/verification-notification', ['email' => 'personne@exemple.fr']);

        $known->assertAccepted();
        $unknown->assertAccepted();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentToTimes($user, VerifyEmailNotification::class, 1);
    }

    public function test_the_account_can_log_in_once_confirmed(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'hugo@exemple.fr']);
        $this->getJson($this->linkFor($user))->assertNoContent();
        $this->app['auth']->guard('web')->logout();

        $this->postJson('/api/login', ['email' => 'hugo@exemple.fr', 'password' => 'password'])->assertSuccessful();

        $this->assertAuthenticatedAs($user->fresh(), 'web');
    }
}
