<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 005, FR-003 and SC-003 — a registration on the address of a confirmed account,
 * active or being deleted, changes nothing and sends nothing.
 */
class RegistrationOnTakenAddressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{bool}>
     */
    public static function confirmedAccounts(): array
    {
        return [
            'active' => [false],
            'being deleted' => [true],
        ];
    }

    #[DataProvider('confirmedAccounts')]
    public function test_a_confirmed_account_is_left_untouched_and_unwarned(bool $isBeingDeleted): void
    {
        Notification::fake();
        $factory = User::factory();
        $account = ($isBeingDeleted ? $factory->pendingDeletion(keepsPublishedSubjects: true, daysAgo: 3) : $factory)
            ->create(['email' => 'camille@exemple.fr', 'display_name' => 'Camille', 'timezone' => 'Europe/Paris']);
        $before = $account->fresh()->getAttributes();

        $this->postJson('/api/register', [
            'display_name' => 'Dominique',
            'email' => 'camille@exemple.fr',
            'password' => 'autremotdepasse',
            'password_confirmation' => 'autremotdepasse',
            'timezone' => 'Asia/Tokyo',
        ])->assertCreated();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($before, $account->fresh()->getAttributes());
        $this->assertTrue(Hash::check('password', $account->fresh()->password));
        Notification::assertNothingSent();
        $this->assertGuest('web');
    }
}
