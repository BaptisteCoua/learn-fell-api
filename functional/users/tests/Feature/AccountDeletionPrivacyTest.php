<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Feature 004, FR-024 and SC-006 — nothing tells an account being deleted from an active one.
 * The unsubscribe link is covered in the reminders layer, the login in the cancellation test.
 */
class AccountDeletionPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email): TestResponse
    {
        return $this->postJson('/api/register', [
            'display_name' => 'Camille Roux',
            'email' => $email,
            'password' => 'motdepasse',
            'password_confirmation' => 'motdepasse',
        ]);
    }

    public function test_signing_up_with_the_address_of_an_account_being_deleted_answers_as_a_taken_address(): void
    {
        User::factory()->pendingDeletion()->create(['email' => 'camille@exemple.fr']);
        User::factory()->create(['email' => 'dominique@exemple.fr']);

        $pending = $this->register('camille@exemple.fr')->assertCreated();
        $active = $this->register('dominique@exemple.fr')->assertCreated();

        $this->assertSame($active->json(), $pending->json());
    }

    public function test_asking_for_a_reset_link_answers_alike(): void
    {
        User::factory()->pendingDeletion()->create(['email' => 'camille@exemple.fr']);
        User::factory()->create(['email' => 'dominique@exemple.fr']);

        $pending = $this->postJson('/api/forgot-password', ['email' => 'camille@exemple.fr']);
        $active = $this->postJson('/api/forgot-password', ['email' => 'dominique@exemple.fr']);
        $unknown = $this->postJson('/api/forgot-password', ['email' => 'personne@exemple.fr']);

        $this->assertSame($active->status(), $pending->status());
        $this->assertSame($active->json(), $pending->json());
        $this->assertSame($unknown->json(), $pending->json());
    }
}
