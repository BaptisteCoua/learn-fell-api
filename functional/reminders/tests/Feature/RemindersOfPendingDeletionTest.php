<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Reminders\Domain\ReminderEligibility;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 004, FR-007 and FR-015 — no reminder while a deletion is pending, and the same
 * reminders as before once it is cancelled.
 */
class RemindersOfPendingDeletionTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-01 19:00', 'Europe/Paris'));
    }

    private function learnerWithDueCards(): ReminderSetting
    {
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $setting = ReminderSetting::query()->where('user_id', $user->id)->sole();
        $setting->forceFill(['email_enabled' => true, 'activated_at' => now()])->save();
        PushSubscription::factory()->for($setting, 'subscribable')->create();
        $this->learn($user, $this->publishedSubjectWithQuestions(3));

        return $setting->fresh();
    }

    public function test_an_account_whose_deletion_is_pending_gets_no_reminder_and_keeps_its_settings(): void
    {
        $setting = $this->learnerWithDueCards();
        $this->assertNotNull(app(ReminderEligibility::class)->for($setting));

        $setting->user->forceFill(['deletion_requested_at' => now()])->save();

        $this->assertNull(app(ReminderEligibility::class)->for($setting->fresh()));
        $this->assertTrue($setting->fresh()->email_enabled);
        $this->assertSame(1, PushSubscription::query()->count());
    }
}
