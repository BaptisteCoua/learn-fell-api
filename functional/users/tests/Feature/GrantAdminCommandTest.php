<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gives_the_admin_permissions_to_an_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'camille@exemple.fr']);

        $this->artisan('users:grant-admin', ['email' => 'Camille@Exemple.fr'])->assertSuccessful();

        $this->assertTrue($user->fresh()->can('categories.manage'));
        $this->assertTrue($user->fresh()->can('subjects.moderate'));
    }

    public function test_it_fails_for_an_unknown_email(): void
    {
        $this->artisan('users:grant-admin', ['email' => 'personne@exemple.fr'])->assertFailed();
    }
}
