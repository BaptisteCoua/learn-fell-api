<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Reminders\Tests\Concerns\ManagesReminders;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-002, FR-003, FR-007 — user story 1: the account's reminder settings.
 */
class ReminderSettingsResourceTest extends TestCase
{
    use ManagesReminders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 10:00', 'Europe/Paris'));
    }

    public function test_an_account_reads_its_own_settings_only(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);
        PushSubscription::factory()->count(2)->for($setting, 'subscribable')->create();
        User::factory()->create();

        $response = $this->searchSettings($user);

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($setting->id, $response->json('data.0.id'));
        $this->assertFalse($response->json('data.0.email_enabled'));
        $this->assertSame('19:00', $response->json('data.0.send_time'));
        $this->assertSame(2, $response->json('data.0.devices_count'));
    }

    public function test_an_account_cannot_change_the_settings_of_another(): void
    {
        $other = $this->settingOf(User::factory()->create());

        $this->updateSettings(User::factory()->create(), $other->id, ['email_enabled' => true])->assertForbidden();

        $this->assertFalse($other->fresh()->email_enabled);
    }

    public function test_an_unconfirmed_address_cannot_reach_the_settings(): void
    {
        $this->searchSettings(User::factory()->unverified()->create())->assertForbidden();
    }

    public function test_a_visitor_cannot_reach_the_settings(): void
    {
        $this->postJson('/api/reminder-settings/search', ['search' => []])->assertUnauthorized();
    }

    public function test_settings_are_neither_created_nor_deleted_through_the_api(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);

        $this->actingAs($user)->postJson('/api/reminder-settings/mutate', [
            'mutate' => [['operation' => 'create', 'attributes' => ['email_enabled' => true]]],
        ])->assertForbidden();
        $this->actingAs($user)->deleteJson('/api/reminder-settings', ['resources' => [$setting->id]])->assertForbidden();

        $this->assertModelExists($setting);
    }

    public function test_turning_the_email_on_schedules_the_first_reminder_at_19_00_local_time(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $setting = $this->settingOf($user);

        $this->updateSettings($user, $setting->id, ['email_enabled' => true])->assertOk();

        $setting->refresh();
        $this->assertTrue($setting->email_enabled);
        $this->assertSame('2026-09-29 17:00', $setting->next_reminder_at->utc()->format('Y-m-d H:i'));
        $this->assertNotNull($setting->activated_at);
        $this->assertNotNull($setting->proposal_seen_at);
    }

    public function test_a_later_change_keeps_the_first_activation_and_the_proposal_date(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);
        $this->updateSettings($user, $setting->id, ['email_enabled' => true]);
        $activatedAt = $setting->fresh()->activated_at;
        $proposalSeenAt = $setting->fresh()->proposal_seen_at;
        $this->travel(2)->days();

        $this->updateSettings($user, $setting->id, ['send_time' => '08:00'])->assertOk();

        $this->assertEquals($activatedAt, $setting->fresh()->activated_at);
        $this->assertEquals($proposalSeenAt, $setting->fresh()->proposal_seen_at);
    }

    public function test_changing_the_time_moves_the_next_reminder(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $setting = $this->settingOf($user);
        $this->updateSettings($user, $setting->id, ['email_enabled' => true]);

        $this->updateSettings($user, $setting->id, ['send_time' => '08:00'])->assertOk();

        $setting->refresh();
        $this->assertSame('08:00', $setting->send_time);
        $this->assertSame('2026-09-30 06:00', $setting->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_turning_the_email_on_after_the_chosen_time_starts_the_next_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 20:00', 'Europe/Paris'));
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $setting = $this->settingOf($user);

        $this->updateSettings($user, $setting->id, ['email_enabled' => true])->assertOk();

        $this->assertSame('2026-09-30 17:00', $setting->fresh()->next_reminder_at->utc()->format('Y-m-d H:i'));
    }

    public function test_turning_every_channel_off_clears_the_next_reminder(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);
        $this->updateSettings($user, $setting->id, ['email_enabled' => true]);

        $this->updateSettings($user, $setting->id, ['email_enabled' => false])->assertOk();

        $this->assertNull($setting->fresh()->next_reminder_at);
    }

    public function test_turning_the_email_back_on_clears_its_stop_and_outdates_the_old_unsubscribe_links(): void
    {
        $setting = ReminderSetting::factory()->bounced()->create(['unsubscribe_version' => 2]);

        $this->updateSettings($setting->user, $setting->id, ['email_enabled' => true])->assertOk();

        $setting->refresh();
        $this->assertNull($setting->email_disabled_reason);
        $this->assertSame(0, $setting->email_bounce_count);
        $this->assertSame(3, $setting->unsubscribe_version);
    }

    public function test_the_same_email_setting_sent_again_keeps_the_unsubscribe_links_valid(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create(['unsubscribe_version' => 2]);

        $this->updateSettings($setting->user, $setting->id, ['email_enabled' => true, 'send_time' => '07:30'])->assertOk();

        $this->assertSame(2, $setting->fresh()->unsubscribe_version);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSendTimes(): array
    {
        return [
            'before 6 h' => ['05:30'],
            'after 23 h 30' => ['23:45'],
            'not on the hour or half hour' => ['19:15'],
            'not a time' => ['sept heures'],
        ];
    }

    #[DataProvider('invalidSendTimes')]
    public function test_the_time_is_between_6_00_and_23_30_on_the_hour_or_half_hour(string $sendTime): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);

        $this->updateSettings($user, $setting->id, ['send_time' => $sendTime])
            ->assertUnprocessable()
            ->assertJson(['code' => 'invalid_send_time']);

        $this->assertSame('19:00', $setting->fresh()->send_time);
    }

    public function test_the_bounds_of_the_time_are_accepted(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);

        $this->updateSettings($user, $setting->id, ['send_time' => '06:00'])->assertOk();
        $this->updateSettings($user, $setting->id, ['send_time' => '23:30'])->assertOk();

        $this->assertSame('23:30', $setting->fresh()->send_time);
    }

    public function test_the_computed_and_managed_fields_cannot_be_written(): void
    {
        $user = User::factory()->create();
        $setting = $this->settingOf($user);

        $this->updateSettings($user, $setting->id, ['unsubscribe_version' => 0])->assertUnprocessable();
        $this->updateSettings($user, $setting->id, ['next_reminder_at' => '2030-01-01 00:00:00'])->assertUnprocessable();
        $this->updateSettings($user, $setting->id, ['email_disabled_reason' => 'bounced'])->assertUnprocessable();
    }
}
