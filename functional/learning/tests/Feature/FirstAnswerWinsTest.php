<?php

namespace Functional\Learning\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Enums\AnswerStatus;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature 006, FR-013 — user story 3: a card reviewed on two devices keeps the first answer given
 * for each due date, whichever arrives first.
 */
class FirstAnswerWinsTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    private const TUESDAY = '2026-10-06';

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['timezone' => 'Europe/Paris']);
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, 'Europe/Paris'));
    }

    public function test_the_answer_given_first_offline_wins_over_the_one_received_first(): void
    {
        $card = $this->learnedCard($this->learner, 2, self::TUESDAY);
        $this->at(self::TUESDAY.' 12:00');
        $this->answer($this->learner, $card->id, true, ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-06T12:00:00+02:00', 'due_on' => self::TUESDAY])->assertOk();
        $this->assertSame(3, $card->fresh()->box);

        $this->at(self::TUESDAY.' 18:00');
        $this->answer($this->learner, $card->id, false, ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-06T08:00:00+02:00', 'due_on' => self::TUESDAY])->assertOk();

        $card->refresh();
        $this->assertSame(1, $card->box);
        $this->assertSame('2026-10-07', $card->next_review_on->toDateString());
        [$morning, $noon] = ReviewAnswer::query()->orderBy('answered_at')->get();
        $this->assertSame([AnswerStatus::Applied, 2, 1], [$morning->status, $morning->from_box, $morning->to_box]);
        $this->assertSame(AnswerStatus::Discarded, $noon->status);
        $this->assertSame($noon->from_box, $noon->to_box);
        $this->assertSame('2026-10-06T06:00:00+00:00', $card->last_answered_at->toIso8601String());
    }

    public function test_a_second_answer_for_the_same_due_date_is_discarded(): void
    {
        $card = $this->learnedCard($this->learner, 2, self::TUESDAY);
        $this->at(self::TUESDAY.' 18:00');

        $this->answer($this->learner, $card->id, true, ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-06T08:00:00+02:00', 'due_on' => self::TUESDAY])->assertOk();
        $this->answer($this->learner, $card->id, false, ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-06T09:00:00+02:00', 'due_on' => self::TUESDAY])->assertOk();

        $this->assertSame([3, '2026-10-10'], [$card->fresh()->box, $card->fresh()->next_review_on->toDateString()]);
        $this->assertSame(
            [AnswerStatus::Applied, AnswerStatus::Discarded],
            ReviewAnswer::query()->orderBy('answered_at')->pluck('status')->all(),
        );
    }

    public function test_a_duplicate_arriving_after_the_next_due_date_does_not_answer_it(): void
    {
        $card = $this->learnedCard($this->learner, 1, self::TUESDAY);
        $this->at(self::TUESDAY.' 10:00');
        $this->answer($this->learner, $card->id, true, ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-06T08:00:00+02:00', 'due_on' => self::TUESDAY])->assertOk();
        $this->assertSame('2026-10-08', $card->fresh()->next_review_on->toDateString());

        $this->at('2026-10-08 18:00');
        $this->answer($this->learner, $card->id, false, ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-06T09:00:00+02:00', 'due_on' => self::TUESDAY])->assertOk();

        $this->assertSame([2, '2026-10-08'], [$card->fresh()->box, $card->fresh()->next_review_on->toDateString()]);
        $this->assertSame(AnswerStatus::Discarded, ReviewAnswer::query()->where('known', false)->sole()->status);
    }
}
