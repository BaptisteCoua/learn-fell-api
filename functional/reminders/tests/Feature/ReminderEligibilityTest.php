<?php

namespace Functional\Reminders\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Catalog\Enums\SubjectStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\Learning;
use Functional\Learning\Queries\DueCardsQuery;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Reminders\Domain\ReminderEligibility;
use Functional\Reminders\Models\PushSubscription;
use Functional\Reminders\Models\ReminderSend;
use Functional\Reminders\Models\ReminderSetting;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FR-008, FR-019, SC-006 — who gets a reminder today, and what it announces.
 */
class ReminderEligibilityTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-29 19:00', 'Europe/Paris'));
    }

    private function learnerWithEmail(?User $user = null): ReminderSetting
    {
        $user ??= User::factory()->create(['timezone' => 'Europe/Paris']);

        return ReminderSetting::factory()->emailEnabled()->for($user)->create();
    }

    private function eligibility(ReminderSetting $setting): ?array
    {
        $reminder = app(ReminderEligibility::class)->for($setting->fresh());

        return $reminder === null ? null : ['cards' => $reminder->cardsCount, 'subjects' => $reminder->subjectIds];
    }

    public function test_the_due_cards_of_every_learned_subject_are_announced(): void
    {
        $setting = $this->learnerWithEmail();
        $verbs = $this->publishedSubjectWithQuestions(7);
        $dates = $this->publishedSubjectWithQuestions(5);
        $this->learn($setting->user, $verbs);
        $this->learn($setting->user, $dates);

        $this->assertSame(['cards' => 12, 'subjects' => [$verbs->id, $dates->id]], $this->eligibility($setting));
    }

    public function test_the_announced_count_is_the_count_of_the_session(): void
    {
        $setting = $this->learnerWithEmail();
        $subject = $this->publishedSubjectWithQuestions(4);
        $this->learn($setting->user, $subject);
        CardProgress::query()->limit(1)->update(['next_review_on' => '2026-10-02']);

        $this->assertSame(3, $this->eligibility($setting)['cards']);
        $this->assertSame(3, DueCardsQuery::forUser($setting->user, [$subject->id])->count());
    }

    public function test_a_device_alone_is_enough(): void
    {
        $user = User::factory()->create();
        $setting = ReminderSetting::query()->where('user_id', $user->id)->sole();
        PushSubscription::factory()->for($setting, 'subscribable')->create();
        $this->learn($user, $this->publishedSubjectWithQuestions(2));

        $this->assertSame(2, $this->eligibility($setting)['cards']);
    }

    public function test_an_unconfirmed_address_gets_nothing(): void
    {
        $setting = $this->learnerWithEmail();
        $this->learn($setting->user, $this->publishedSubjectWithQuestions(2));
        $setting->user->forceFill(['email_verified_at' => null])->save();

        $this->assertNull($this->eligibility($setting));
    }

    public function test_no_active_channel_means_nothing(): void
    {
        $user = User::factory()->create();
        $this->learn($user, $this->publishedSubjectWithQuestions(2));

        $this->assertNull($this->eligibility(ReminderSetting::query()->where('user_id', $user->id)->sole()));
    }

    public function test_an_account_that_learns_nothing_gets_nothing(): void
    {
        $setting = $this->learnerWithEmail();
        $this->learn($setting->user, $this->publishedSubjectWithQuestions(2));
        Learning::query()->where('user_id', $setting->user_id)->each(fn (Learning $learning) => $learning->delete());

        $this->assertNull($this->eligibility($setting));
    }

    public function test_no_card_due_today_means_nothing(): void
    {
        $setting = $this->learnerWithEmail();
        $this->learn($setting->user, $this->publishedSubjectWithQuestions(2));
        CardProgress::query()->update(['next_review_on' => '2026-09-30']);

        $this->assertNull($this->eligibility($setting));
    }

    public function test_cards_of_unpublished_or_retired_subjects_do_not_count(): void
    {
        $setting = $this->learnerWithEmail();
        $unpublished = $this->publishedSubjectWithQuestions(2);
        $retired = $this->publishedSubjectWithQuestions(3);
        $this->learn($setting->user, $unpublished);
        $this->learn($setting->user, $retired);
        $unpublished->forceFill(['status' => SubjectStatus::Draft])->save();
        $retired->forceFill(['status' => SubjectStatus::Retired])->save();

        $this->assertNull($this->eligibility($setting));
    }

    public function test_only_the_published_subjects_are_in_the_link(): void
    {
        $setting = $this->learnerWithEmail();
        $published = $this->publishedSubjectWithQuestions(2);
        $retired = $this->publishedSubjectWithQuestions(3);
        $this->learn($setting->user, $published);
        $this->learn($setting->user, $retired);
        $retired->forceFill(['status' => SubjectStatus::Retired])->save();

        $this->assertSame(['cards' => 2, 'subjects' => [$published->id]], $this->eligibility($setting));
    }

    public function test_today_is_the_day_of_the_account_time_zone(): void
    {
        $user = User::factory()->create(['timezone' => 'Pacific/Auckland']);
        $setting = $this->learnerWithEmail($user);
        $this->learn($user, $this->publishedSubjectWithQuestions(2));
        CardProgress::query()->update(['next_review_on' => '2026-09-30']);

        $this->assertSame(2, $this->eligibility($setting)['cards']);
    }

    public function test_an_answer_sent_late_spaces_the_reminders_from_the_day_it_was_given(): void
    {
        $setting = $this->learnerWithEmail();
        $card = $this->learnedCard($setting->user, 1, '2026-09-19');
        ReminderSend::factory()->create(['user_id' => $setting->user_id, 'local_date' => '2026-09-28']);

        // Given ten days ago, sent today: ten days without review, so every other day.
        $this->answer($setting->user, $card->id, true, [
            'answer_id' => (string) Str::uuid(),
            'answered_at' => '2026-09-19T12:00:00+02:00',
            'due_on' => '2026-09-19',
        ])->assertOk();

        $this->assertNull($this->eligibility($setting));
    }
}
