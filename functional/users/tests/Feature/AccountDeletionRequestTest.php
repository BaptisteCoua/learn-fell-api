<?php

namespace Functional\Users\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Users\Events\AccountDeletionRequested;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature 004, user story 1 — FR-002, FR-003, FR-006, FR-013.
 */
class AccountDeletionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 22:30', 'Europe/Paris'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestDeletion(User $user, array $payload = ['password' => 'password']): TestResponse
    {
        return $this->actingAs($user, 'web')->postJson('/api/account/deletion', $payload);
    }

    private function openSessionFor(User $user, string $sessionId): void
    {
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Firefox',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_the_screen_gives_the_erasure_date_in_the_account_time_zone(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

        $this->actingAs($user, 'web')->getJson('/api/account/deletion')
            ->assertOk()
            ->assertExactJson(['can_request' => true, 'blocked_reason' => null, 'erase_on' => '2026-11-01']);
    }

    public function test_a_wrong_password_changes_nothing(): void
    {
        $user = User::factory()->create();

        $this->requestDeletion($user, ['password' => 'mauvais'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertFalse($user->fresh()->isPendingDeletion());
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_the_request_closes_every_session_of_the_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->openSessionFor($user, 'phone');
        $this->openSessionFor($user, 'laptop');
        $this->openSessionFor($other, 'other');

        $this->requestDeletion($user)->assertOk()->assertExactJson(['erase_on' => '2026-10-31']);

        $user->refresh();
        $this->assertTrue($user->isPendingDeletion());
        $this->assertNull($user->keeps_published_subjects);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->getKey())->count());
        $this->assertGuest('web');
    }

    public function test_the_account_no_longer_reaches_its_data_after_the_request(): void
    {
        $user = User::factory()->create();

        $this->requestDeletion($user)->assertOk();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_the_request_is_announced_to_the_other_layers(): void
    {
        Event::fake([AccountDeletionRequested::class]);
        $user = User::factory()->create();

        $this->requestDeletion($user)->assertOk();

        Event::assertDispatched(AccountDeletionRequested::class, fn (AccountDeletionRequested $event): bool => $event->user->is($user));
    }

    public function test_a_visitor_cannot_request_anything(): void
    {
        $this->getJson('/api/account/deletion')->assertUnauthorized();
        $this->postJson('/api/account/deletion', ['password' => 'password'])->assertUnauthorized();
    }
}
