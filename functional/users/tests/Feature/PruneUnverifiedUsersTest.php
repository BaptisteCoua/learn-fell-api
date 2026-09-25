<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-002 — an account never confirmed within 7 days is removed (research R3).
 */
class PruneUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_unconfirmed_accounts_older_than_7_days_are_removed(): void
    {
        $stale = User::factory()->unverified()->create(['created_at' => now()->subDays(8)]);
        $recent = User::factory()->unverified()->create(['created_at' => now()->subDays(6)]);
        $confirmed = User::factory()->create(['created_at' => now()->subDays(30)]);

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($recent);
        $this->assertModelExists($confirmed);
    }
}
