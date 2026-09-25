<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Functional\Users\Support\AccountLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * FR-003, FR-004, FR-006 — scenarios 4, 7, 8 and 10 of user story 2.
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function logIn(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/login', [
            'email' => 'camille@exemple.fr',
            'password' => 'password',
            ...$overrides,
        ]);
    }

    public function test_an_active_account_logs_in_and_keeps_its_device_time_zone(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr', 'timezone' => 'Europe/Paris']);

        $this->logIn(['email' => 'Camille@Exemple.fr', 'timezone' => 'Asia/Tokyo'])->assertSuccessful();

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertSame('Asia/Tokyo', $user->fresh()->timezone);
    }

    public function test_the_session_lasts_30_days(): void
    {
        $this->assertSame(60 * 24 * 30, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }

    public function test_a_wrong_password_and_an_unknown_email_get_the_same_answer(): void
    {
        User::factory()->create(['email' => 'camille@exemple.fr']);

        $wrongPassword = $this->logIn(['password' => 'mauvais']);
        $unknownEmail = $this->logIn(['email' => 'personne@exemple.fr']);

        $wrongPassword->assertUnprocessable()->assertJson(['code' => 'invalid_credentials']);
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertGuest('web');
    }

    public function test_an_unconfirmed_account_is_reported_only_with_the_right_password(): void
    {
        User::factory()->unverified()->create(['email' => 'camille@exemple.fr']);

        $this->logIn(['password' => 'mauvais'])->assertJson(['code' => 'invalid_credentials']);
        $this->logIn()->assertUnprocessable()->assertJson(['code' => 'email_not_verified']);
        $this->assertGuest('web');
    }

    public function test_five_failures_lock_the_account_for_15_minutes(): void
    {
        User::factory()->create(['email' => 'camille@exemple.fr']);

        foreach (range(1, AccountLockout::MAX_FAILURES) as $attempt) {
            $this->logIn(['password' => 'mauvais'])->assertJson(['code' => 'invalid_credentials']);
        }

        $locked = $this->logIn();
        $locked->assertTooManyRequests()->assertJson(['code' => 'locked']);
        $this->assertGreaterThan(14 * 60, $locked->json('retry_after'));
        $this->assertStringContainsString('15 minutes', $locked->json('message'));
        $this->assertGuest('web');

        $this->travel(15)->minutes();
        $this->logIn()->assertSuccessful();
    }

    public function test_a_success_resets_the_failure_count(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr']);

        foreach (range(1, AccountLockout::MAX_FAILURES - 1) as $attempt) {
            $this->logIn(['password' => 'mauvais']);
        }
        $this->logIn()->assertSuccessful();
        $this->app['auth']->guard('web')->logout();

        $this->logIn(['password' => 'mauvais'])->assertJson(['code' => 'invalid_credentials']);
        $this->logIn()->assertSuccessful();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_a_logged_out_account_no_longer_reaches_its_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->getJson('/api/user')->assertOk()->assertJson([
            'id' => $user->id,
            'display_name' => $user->display_name,
            'email' => $user->email,
            'permissions' => [],
        ]);

        $this->postJson('/api/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/user')->assertUnauthorized();
    }
}
