<?php

namespace Functional\Learning\Tests\Feature;

use Carbon\CarbonImmutable;
use Functional\Learning\Enums\AnswerStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Functional\Learning\Tests\Concerns\ReviewsCards;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 006, FR-011, FR-012, FR-015, FR-016, SC-002, SC-003 — answers given offline count on the
 * day they were given, in the order they were given, once.
 */
class DatedAnswersTest extends TestCase
{
    use RefreshDatabase, ReviewsCards;

    private const MONDAY = '2026-10-05';

    private const WEDNESDAY = '2026-10-07';

    private const RECEIVED_AT = '2026-10-07T16:00:00+00:00';

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['timezone' => 'Europe/Paris']);
        $this->travelTo(CarbonImmutable::parse(self::WEDNESDAY.' 18:00', 'Europe/Paris'));
    }

    /**
     * @return array{box: int, next_review_on: string, last_answered_at: string|null}
     */
    private function stateOf(CardProgress $card): array
    {
        $card->refresh();

        return [
            'box' => $card->box,
            'next_review_on' => $card->next_review_on->toDateString(),
            'last_answered_at' => $card->last_answered_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{known: bool, from_box: int, to_box: int, status: AnswerStatus, answered_at: string, due_on: string}>
     */
    private function answersOf(CardProgress $card): array
    {
        return ReviewAnswer::query()->where('card_progress_id', $card->id)->orderBy('answered_at')->get()
            ->map(fn (ReviewAnswer $answer): array => [
                'known' => $answer->known,
                'from_box' => $answer->from_box,
                'to_box' => $answer->to_box,
                'status' => $answer->status,
                'answered_at' => $answer->answered_at->toIso8601String(),
                'due_on' => $answer->due_on->toDateString(),
            ])->all();
    }

    public function test_an_answer_sent_on_wednesday_counts_on_the_monday_it_was_given(): void
    {
        $card = $this->learnedCard($this->learner, 2, self::MONDAY);

        $this->answer($this->learner, $card->id, true, [
            'answer_id' => (string) Str::uuid(),
            'answered_at' => '2026-10-05T08:12:31+02:00',
            'due_on' => self::MONDAY,
        ])->assertOk();

        $this->assertSame(['box' => 3, 'next_review_on' => '2026-10-09', 'last_answered_at' => '2026-10-05T06:12:31+00:00'], $this->stateOf($card));
        $this->assertSame([[
            'known' => true,
            'from_box' => 2,
            'to_box' => 3,
            'status' => AnswerStatus::Applied,
            'answered_at' => '2026-10-05T06:12:31+00:00',
            'due_on' => self::MONDAY,
        ]], $this->answersOf($card));
    }

    public function test_two_answers_received_out_of_order_end_as_if_received_in_order(): void
    {
        $mondayAnswer = ['answered_at' => '2026-10-05T08:00:00+02:00', 'due_on' => self::MONDAY];
        $wednesdayAnswer = ['answered_at' => '2026-10-07T09:00:00+02:00', 'due_on' => self::WEDNESDAY];

        $inOrder = $this->learnedCard($this->learner, 1, self::MONDAY);
        $this->answer($this->learner, $inOrder->id, true, ['answer_id' => (string) Str::uuid(), ...$mondayAnswer])->assertOk();
        $this->answer($this->learner, $inOrder->id, true, ['answer_id' => (string) Str::uuid(), ...$wednesdayAnswer])->assertOk();

        $outOfOrder = $this->learnedCard($this->learner, 1, self::MONDAY);
        $this->answer($this->learner, $outOfOrder->id, true, ['answer_id' => (string) Str::uuid(), ...$wednesdayAnswer])->assertOk();
        $this->answer($this->learner, $outOfOrder->id, true, ['answer_id' => (string) Str::uuid(), ...$mondayAnswer])->assertOk();

        $this->assertSame(['box' => 3, 'next_review_on' => '2026-10-11', 'last_answered_at' => '2026-10-07T07:00:00+00:00'], $this->stateOf($inOrder));
        $this->assertSame($this->stateOf($inOrder), $this->stateOf($outOfOrder));
        $this->assertSame($this->answersOf($inOrder), $this->answersOf($outOfOrder));
        $this->assertSame([AnswerStatus::Applied, AnswerStatus::Applied], array_column($this->answersOf($outOfOrder), 'status'));
    }

    public function test_the_same_answer_sent_twice_counts_once(): void
    {
        $card = $this->learnedCard($this->learner, 2, self::MONDAY);
        $answer = ['answer_id' => (string) Str::uuid(), 'answered_at' => '2026-10-05T08:00:00+02:00', 'due_on' => self::MONDAY];

        $first = $this->answer($this->learner, $card->id, true, $answer)->assertOk();
        $second = $this->answer($this->learner, $card->id, true, $answer)->assertOk();

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, ReviewAnswer::query()->count());
        $this->assertSame(3, $card->fresh()->box);
    }

    public function test_an_answer_dated_in_the_future_counts_when_it_is_received(): void
    {
        $card = $this->learnedCard($this->learner, 1, self::WEDNESDAY);

        $this->answer($this->learner, $card->id, true, [
            'answer_id' => (string) Str::uuid(),
            'answered_at' => '2026-10-12T08:00:00+02:00',
            'due_on' => self::WEDNESDAY,
        ])->assertOk();

        $this->assertSame(['box' => 2, 'next_review_on' => '2026-10-09', 'last_answered_at' => self::RECEIVED_AT], $this->stateOf($card));
        $this->assertSame(self::RECEIVED_AT, ReviewAnswer::query()->sole()->answered_at->toIso8601String());
    }

    public function test_an_answer_from_a_clock_running_late_counts_when_received_if_the_card_is_due_then(): void
    {
        $card = $this->learnedCard($this->learner, 1, self::WEDNESDAY);

        $this->answer($this->learner, $card->id, false, [
            'answer_id' => (string) Str::uuid(),
            'answered_at' => '2026-10-06T20:00:00+02:00',
            'due_on' => self::WEDNESDAY,
        ])->assertOk();

        $this->assertSame(['box' => 1, 'next_review_on' => '2026-10-08', 'last_answered_at' => self::RECEIVED_AT], $this->stateOf($card));
        $answer = ReviewAnswer::query()->sole();
        $this->assertSame(AnswerStatus::Applied, $answer->status);
        $this->assertSame(self::RECEIVED_AT, $answer->answered_at->toIso8601String());
    }

    public function test_an_answer_from_a_clock_running_late_is_discarded_if_the_card_is_not_due_yet(): void
    {
        $card = $this->learnedCard($this->learner, 3, '2026-10-08');
        $before = $this->stateOf($card);

        $this->answer($this->learner, $card->id, true, [
            'answer_id' => (string) Str::uuid(),
            'answered_at' => '2026-10-06T20:00:00+02:00',
            'due_on' => '2026-10-08',
        ])->assertOk();

        $this->assertSame($before, $this->stateOf($card));
        $answer = ReviewAnswer::query()->sole();
        $this->assertSame(AnswerStatus::Discarded, $answer->status);
        $this->assertSame([3, 3], [$answer->from_box, $answer->to_box]);
    }

    public function test_an_answer_without_the_offline_fields_counts_now_on_the_current_due_date(): void
    {
        $card = $this->learnedCard($this->learner, 2, self::MONDAY);

        $this->answer($this->learner, $card->id, true)->assertOk();

        $this->assertSame(['box' => 3, 'next_review_on' => '2026-10-11', 'last_answered_at' => self::RECEIVED_AT], $this->stateOf($card));
        $answer = ReviewAnswer::query()->sole();
        $this->assertTrue(Str::isUuid($answer->answer_id));
        $this->assertSame(self::MONDAY, $answer->due_on->toDateString());
        $this->assertSame(AnswerStatus::Applied, $answer->status);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformedFields(): array
    {
        return [
            'answer id' => [['answer_id' => 'not-a-uuid']],
            'answered at' => [['answered_at' => 'not a date']],
            'due on' => [['due_on' => '05/10/2026']],
            'known' => [['known' => 'maybe']],
            'card' => [['card_progress_id' => 'first']],
        ];
    }

    /**
     * @param  array<string, mixed>  $malformed
     */
    #[DataProvider('malformedFields')]
    public function test_a_malformed_field_is_refused(array $malformed): void
    {
        $card = $this->learnedCard($this->learner, 2, self::MONDAY);
        $cardId = $malformed['card_progress_id'] ?? $card->id;
        $known = $malformed['known'] ?? true;
        unset($malformed['card_progress_id'], $malformed['known']);

        $this->answer($this->learner, $cardId, $known, $malformed)->assertUnprocessable();

        $this->assertSame(0, ReviewAnswer::query()->count());
    }
}
