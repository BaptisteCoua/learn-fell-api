<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Events\AccountDeletionCancelled;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature 004, user story 3 — FR-014, FR-015 and FR-024: logging in during the grace period
 * cancels the request, and nothing else tells a pending account from an active one.
 */
class AccountDeletionCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function logIn(string $password = 'password'): TestResponse
    {
        return $this->postJson('/api/login', ['email' => 'camille@exemple.fr', 'password' => $password]);
    }

    public function test_logging_in_cancels_a_pending_request(): void
    {
        Event::fake([AccountDeletionCancelled::class]);
        $user = User::factory()->pendingDeletion(keepsPublishedSubjects: false, daysAgo: 10)->create(['email' => 'camille@exemple.fr']);

        $this->logIn()->assertOk()->assertJson(['deletion_cancelled' => true]);

        $user->refresh();
        $this->assertFalse($user->isPendingDeletion());
        $this->assertNull($user->keeps_published_subjects);
        $this->assertAuthenticatedAs($user, 'web');
        Event::assertDispatched(AccountDeletionCancelled::class, fn (AccountDeletionCancelled $event): bool => $event->user->is($user));
    }

    public function test_an_active_account_logs_in_without_the_flag(): void
    {
        Event::fake([AccountDeletionCancelled::class]);
        User::factory()->create(['email' => 'camille@exemple.fr']);

        $response = $this->logIn()->assertOk();

        $this->assertArrayNotHasKey('deletion_cancelled', $response->json() ?? []);
        Event::assertNotDispatched(AccountDeletionCancelled::class);
    }

    public function test_a_wrong_password_gets_the_answer_of_an_active_account(): void
    {
        User::factory()->pendingDeletion()->create(['email' => 'camille@exemple.fr']);
        User::factory()->create(['email' => 'dominique@exemple.fr']);

        $pending = $this->logIn('mauvais')->assertUnprocessable();
        $active = $this->postJson('/api/login', ['email' => 'dominique@exemple.fr', 'password' => 'mauvais'])->assertUnprocessable();

        $this->assertSame($active->json(), $pending->json());
    }

    public function test_logging_in_after_a_password_reset_cancels_the_request_too(): void
    {
        $user = User::factory()->pendingDeletion()->create(['email' => 'camille@exemple.fr']);

        $this->postJson('/api/reset-password', [
            'token' => Password::broker()->createToken($user),
            'email' => 'camille@exemple.fr',
            'password' => 'nouveaumotdepasse',
            'password_confirmation' => 'nouveaumotdepasse',
        ])->assertSuccessful();
        $this->assertTrue($user->fresh()->isPendingDeletion());

        $this->logIn('nouveaumotdepasse')->assertOk()->assertJson(['deletion_cancelled' => true]);
        $this->assertFalse($user->fresh()->isPendingDeletion());
    }

    public function test_the_permissions_survive_the_round_trip(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr'])->assignRole('admin');
        $user->forceFill(['deletion_requested_at' => now()])->save();

        $this->logIn()->assertOk();

        $this->assertTrue($user->fresh()->can('subjects.moderate'));
    }
}
