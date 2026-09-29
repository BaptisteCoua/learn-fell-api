<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Reminders\Domain\ReminderEligibility;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-012, FR-013 — user story 3: the spacing applied to an account with cards due today.
 */
class ReminderSpacingEligibilityTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    private const TODAY = '2026-09-29';

    private ReminderSetting $setting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::TODAY.' 19:00', 'Europe/Paris'));
        $user = User::factory()->create(['timezone' => 'Europe/Paris']);
        $this->setting = ReminderSetting::factory()->emailEnabled()->for($user)->create([
            'activated_at' => CarbonImmutable::parse(self::TODAY)->subDays(60),
        ]);
        $this->learn($user, $this->publishedSubjectWithQuestions(3));
    }

    private function answeredDaysAgo(int $days): void
    {
        $card = CardProgress::query()->where('user_id', $this->setting->user_id)->firstOrFail();

        ReviewAnswer::query()->create([
            'card_progress_id' => $card->id,
            'user_id' => $this->setting->user_id,
            'known' => true,
            'from_box' => 1,
            'to_box' => 2,
            'answered_at' => CarbonImmutable::parse(self::TODAY.' 12:00', 'Europe/Paris')->subDays($days),
        ]);
    }

    private function remindedDaysAgo(int $days): void
    {
        ReminderSend::factory()->create([
            'user_id' => $this->setting->user_id,
            'local_date' => CarbonImmutable::parse(self::TODAY)->subDays($days)->toDateString(),
        ]);
    }

    private function isReminded(): bool
    {
        return app(ReminderEligibility::class)->for($this->setting->fresh()) !== null;
    }

    public function test_three_days_without_review_still_means_a_daily_reminder(): void
    {
        $this->answeredDaysAgo(3);
        $this->remindedDaysAgo(1);

        $this->assertTrue($this->isReminded());
    }

    public function test_ten_days_without_review_means_every_other_day(): void
    {
        $this->answeredDaysAgo(10);
        $this->remindedDaysAgo(1);

        $this->assertFalse($this->isReminded());
    }

    public function test_thirty_days_without_review_means_once_a_week(): void
    {
        $this->answeredDaysAgo(30);
        $this->remindedDaysAgo(6);

        $this->assertFalse($this->isReminded());
    }

    public function test_an_account_that_never_answered_counts_from_its_activation(): void
    {
        $this->remindedDaysAgo(3);

        $this->assertFalse($this->isReminded());

        $this->setting->update(['activated_at' => CarbonImmutable::parse(self::TODAY)->subDays(2)]);
        $this->assertTrue($this->isReminded());
    }

    public function test_an_answer_brings_back_a_reminder_the_next_day(): void
    {
        $this->answeredDaysAgo(30);
        $this->remindedDaysAgo(6);
        $this->assertFalse($this->isReminded());

        $this->answeredDaysAgo(1);

        $this->assertTrue($this->isReminded());
    }

    public function test_the_days_without_review_are_counted_in_the_account_time_zone(): void
    {
        // 29 September in both Paris and Auckland; the answer is on the 21st in Paris, the 22nd in Auckland.
        $this->travelTo(CarbonImmutable::parse('2026-09-29 08:00', 'Europe/Paris'));
        $this->setting->user->update(['timezone' => 'Pacific/Auckland']);
        ReviewAnswer::query()->create([
            'card_progress_id' => CardProgress::query()->where('user_id', $this->setting->user_id)->firstOrFail()->id,
            'user_id' => $this->setting->user_id,
            'known' => true,
            'from_box' => 1,
            'to_box' => 2,
            'answered_at' => CarbonImmutable::parse('2026-09-21 23:30', 'Europe/Paris'),
        ]);
        $this->remindedDaysAgo(1);

        // 7 days without review in Auckland: still daily. It would be 8 in Paris: every other day.
        $this->assertTrue($this->isReminded());
    }
}
