<?php

namespace Functional\Reminders\Tests\Feature;

use Functional\Reminders\Database\Seeders\RemindersSeeder;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemindersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_turns_the_email_reminders_on_for_a_few_confirmed_accounts(): void
    {
        $this->seed(UsersSeeder::class);

        $this->seed(RemindersSeeder::class);

        $enabled = ReminderSetting::query()->where('email_enabled', true)->with('user')->get();
        $this->assertCount(3, $enabled);
        $this->assertTrue($enabled->every(fn (ReminderSetting $setting): bool => $setting->user->hasVerifiedEmail()));
        $this->assertTrue($enabled->every(fn (ReminderSetting $setting): bool => $setting->next_reminder_at !== null));
        $this->assertGreaterThan(0, ReminderSend::query()->count());
    }
}
