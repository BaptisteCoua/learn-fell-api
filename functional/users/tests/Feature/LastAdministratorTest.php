<?php

namespace Functional\Users\Tests\Feature;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 004, user story 5 — FR-005: the last account holding an administration permission
 * cannot be deleted.
 */
class LastAdministratorTest extends TestCase
{
    use RefreshDatabase;

    private function screen(User $user): TestResponse
    {
        return $this->actingAs($user, 'web')->getJson('/api/account/deletion');
    }

    private function requestDeletion(User $user): TestResponse
    {
        $response = $this->actingAs($user, 'web')->postJson('/api/account/deletion', ['password' => 'password']);
        $this->app['auth']->forgetGuards();

        return $response;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function permissions(): array
    {
        return [
            'categories' => ['categories.manage'],
            'moderation' => ['subjects.moderate'],
            'reports' => ['reports.review'],
            'history' => ['moderation.history.view'],
        ];
    }

    #[DataProvider('permissions')]
    public function test_the_only_holder_of_an_administration_permission_cannot_leave(string $permission): void
    {
        $admin = User::factory()->create()->givePermissionTo($permission);

        $this->screen($admin)->assertOk()->assertJson(['can_request' => false, 'blocked_reason' => 'last_admin']);
        $this->requestDeletion($admin)->assertUnprocessable()->assertJson(['code' => 'last_admin']);
        $this->assertFalse($admin->fresh()->isPendingDeletion());
    }

    public function test_with_two_administrators_one_can_leave_and_the_other_becomes_the_last(): void
    {
        $first = User::factory()->create()->assignRole('admin');
        $second = User::factory()->create()->assignRole('admin');

        $this->screen($first)->assertJson(['can_request' => true, 'blocked_reason' => null]);
        $this->requestDeletion($first)->assertOk();

        $this->screen($second)->assertJson(['can_request' => false, 'blocked_reason' => 'last_admin']);
        $this->requestDeletion($second)->assertUnprocessable()->assertJson(['code' => 'last_admin']);

        $this->postJson('/api/login', ['email' => $first->email, 'password' => 'password'])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->requestDeletion($second)->assertOk();
    }

    public function test_a_member_without_permission_is_never_blocked(): void
    {
        $this->screen(User::factory()->create())->assertJson(['can_request' => true]);
    }
}
