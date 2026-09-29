<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Tests\Concerns\ManagesReminders;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-004, FR-006 — user story 1: the devices that show the reminders.
 */
class PushSubscriptionsResourceTest extends TestCase
{
    use ManagesReminders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00', 'Europe/Paris'));
    }

    public function test_registering_a_device_turns_the_push_channel_on(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);

        $this->registerDevice($user)->assertOk();

        $setting = $this->settingOf($user);
        $device = $setting->pushSubscriptions()->sole();
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', $device->endpoint);
        $this->assertSame('Chrome sur Android', $device->device_label);
        $this->assertNotNull($setting->activated_at);
        $this->assertNotNull($setting->proposal_seen_at);
        $this->assertSame('2026-09-29 17:00', $setting->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_registering_the_same_device_again_updates_it(): void
    {
        $user = User::factory()->create();
        $this->registerDevice($user);

        $this->registerDevice($user, ['auth_token' => 'new-auth-token'])->assertOk();

        $this->assertSame('new-auth-token', $this->settingOf($user)->pushSubscriptions()->sole()->auth_token);
    }

    public function test_a_browser_known_for_another_account_moves_to_the_signed_in_account(): void
    {
        $previousOwner = User::factory()->create();
        $user = User::factory()->create();
        $this->registerDevice($previousOwner);

        $this->registerDevice($user)->assertOk();

        $this->assertFalse($this->settingOf($previousOwner)->pushSubscriptions()->exists());
        $this->assertSame(1, $this->settingOf($user)->pushSubscriptions()->count());
        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidDevices(): array
    {
        return [
            'an endpoint that is not https' => [['endpoint' => 'http://push.example.test/abc']],
            'an endpoint that is not a URL' => [['endpoint' => 'not a url']],
            'an endpoint over 1024 characters' => [['endpoint' => 'https://push.example.test/'.str_repeat('a', 1000)]],
            'an unknown content encoding' => [['content_encoding' => 'gzip']],
            'a device name over 60 characters' => [['device_label' => str_repeat('a', 61)]],
            'no public key' => [['public_key' => '']],
        ];
    }

    /**
     * @param  array<string, mixed>  $invalidField
     */
    #[DataProvider('invalidDevices')]
    public function test_an_invalid_device_is_refused(array $invalidField): void
    {
        $user = User::factory()->create();

        $this->registerDevice($user, $invalidField)
            ->assertUnprocessable()
            ->assertJson(['code' => 'invalid_push_subscription']);

        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    public function test_the_list_shows_the_account_devices_newest_first_without_their_keys(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);
        $older = PushSubscription::factory()->for($setting, 'subscribable')->create(['created_at' => now()->subDays(3)]);
        $newer = PushSubscription::factory()->for($setting, 'subscribable')->create(['created_at' => now()->subDay()]);
        PushSubscription::factory()->create();

        $response = $this->searchDevices($user);

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$newer->id, $older->id], array_column($response->json('data'), 'id'));
        $this->assertArrayNotHasKey('public_key', $response->json('data.0'));
        $this->assertArrayNotHasKey('auth_token', $response->json('data.0'));
        $this->assertSame($newer->device_label, $response->json('data.0.device_label'));
    }

    public function test_turning_off_one_device_keeps_the_others(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);
        [$phone, $computer] = PushSubscription::factory()->count(2)->for($setting, 'subscribable')->create();

        $this->deleteDevice($user, $computer->id)->assertOk();

        $this->assertModelMissing($computer);
        $this->assertModelExists($phone);
    }

    public function test_turning_off_the_last_channel_clears_the_next_reminder(): void
    {
        $user = User::factory()->create();
        $this->registerDevice($user);
        $device = $this->settingOf($user)->pushSubscriptions()->sole();

        $this->deleteDevice($user, $device->id)->assertOk();

        $this->assertNull($this->settingOf($user)->next_reminder_at);
    }

    public function test_an_account_cannot_turn_off_the_device_of_another(): void
    {
        $device = PushSubscription::factory()->create();

        $this->deleteDevice(User::factory()->create(), $device->id)->assertForbidden();

        $this->assertModelExists($device);
    }

    public function test_devices_are_not_written_through_mutate(): void
    {
        $user = User::factory()->create();
        $device = PushSubscription::factory()->for($this->settingOf($user), 'subscribable')->create();

        $this->actingAs($user)->postJson('/api/push-subscriptions/mutate', [
            'mutate' => [['operation' => 'update', 'key' => $device->id, 'attributes' => ['device_label' => 'Autre']]],
        ])->assertForbidden();
        $this->actingAs($user)->postJson('/api/push-subscriptions/mutate', [
            'mutate' => [['operation' => 'create', 'attributes' => ['endpoint' => 'https://push.example.test/x', 'device_label' => 'Autre']]],
        ])->assertForbidden();
    }

    public function test_an_unconfirmed_address_cannot_register_a_device(): void
    {
        $this->registerDevice(User::factory()->unverified()->create())->assertForbidden();

        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
