<?php

namespace Functional\Reminders\Tests\Feature;

use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-001 — every account has its settings, off by default, and they go with the account.
 */
class ReminderSettingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_has_its_reminders_turned_off(): void
    {
        $user = User::factory()->create();

        $setting = ReminderSetting::query()->where('user_id', $user->id)->sole();

        $this->assertFalse($setting->email_enabled);
        $this->assertSame('19:00', $setting->send_time);
        $this->assertFalse($setting->pushSubscriptions()->exists());
        $this->assertNull($setting->next_reminder_at);
        $this->assertNull($setting->activated_at);
        $this->assertNull($setting->proposal_seen_at);
    }

    public function test_the_accounts_created_before_the_feature_get_their_settings(): void
    {
        $user = User::factory()->create();
        ReminderSetting::query()->where('user_id', $user->id)->delete();

        $migration = require __DIR__.'/../../database/migrations/2026_09_29_100300_create_reminder_settings_for_existing_users.php';
        $migration->up();
        $migration->up();

        $this->assertSame(1, ReminderSetting::query()->where('user_id', $user->id)->count());
    }

    public function test_deleting_an_account_deletes_its_settings_devices_and_log(): void
    {
        $setting = ReminderSetting::factory()->emailEnabled()->create();
        PushSubscription::factory()->count(2)->for($setting, 'subscribable')->create();
        ReminderSend::factory()->create(['user_id' => $setting->user_id]);

        $setting->user->delete();

        $this->assertDatabaseCount('reminder_settings', 0);
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertDatabaseCount('reminder_sends', 0);
    }
}
