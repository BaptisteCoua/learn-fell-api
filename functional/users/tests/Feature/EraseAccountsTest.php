<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature 004, user story 4 — FR-016, FR-022, FR-023 and FR-024: the daily prune erases an
 * account 30 days after its request, all at once or not at all.
 */
class EraseAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function prune(): void
    {
        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();
    }

    public function test_an_account_is_erased_30_days_after_its_request_and_not_before(): void
    {
        $due = User::factory()->pendingDeletion(daysAgo: 31)->create();
        $notYet = User::factory()->pendingDeletion(daysAgo: 29)->create();
        $active = User::factory()->create(['created_at' => now()->subYear()]);

        $this->prune();

        $this->assertModelMissing($due);
        $this->assertModelExists($notYet);
        $this->assertModelExists($active);
    }

    public function test_sessions_reset_tokens_and_roles_go_with_the_account(): void
    {
        $user = User::factory()->pendingDeletion(daysAgo: 31)->create()->assignRole('admin');
        User::factory()->create()->assignRole('admin');
        DB::table('sessions')->insert([
            'id' => 'left-open', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);
        Password::broker()->createToken($user);

        $this->prune();

        $this->assertModelMissing($user);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $user->id)->count());
    }

    public function test_a_failing_layer_leaves_the_whole_account_for_the_next_prune(): void
    {
        $user = User::factory()->pendingDeletion(daysAgo: 31)->create();
        DB::table('sessions')->insert([
            'id' => 'left-open', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null,
            'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);
        $fails = true;
        Event::listen('eloquent.deleting: '.User::class, function () use (&$fails): void {
            if ($fails) {
                throw new RuntimeException('A layer could not erase its data.');
            }
        });

        $this->prune();
        $this->assertModelExists($user);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());

        $fails = false;
        $this->prune();
        $this->assertModelMissing($user);
    }

    public function test_the_address_of_an_erased_account_is_free_again(): void
    {
        User::factory()->pendingDeletion(daysAgo: 31)->create(['email' => 'camille@exemple.fr']);

        $this->prune();

        $this->postJson('/api/login', ['email' => 'camille@exemple.fr', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJson(['code' => 'invalid_credentials']);
        $this->postJson('/api/register', [
            'display_name' => 'Camille Roux',
            'email' => 'camille@exemple.fr',
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ])->assertCreated();
        $this->assertFalse(User::query()->where('email', 'camille@exemple.fr')->sole()->isPendingDeletion());
    }

    public function test_unconfirmed_accounts_are_still_pruned_after_7_days(): void
    {
        $stale = User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);

        $this->prune();

        $this->assertModelMissing($stale);
    }
}
