<?php

namespace Functional\Learning\Tests\Unit;

use Carbon\CarbonImmutable;
use Functional\Learning\Domain\CardAnswerReplay;
use Functional\Learning\Enums\AnswerStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature 006, research R3 and R4, SC-005 — the replay of a card's answers, step by step.
 */
class CardAnswerReplayTest extends TestCase
{
    private function card(int $box, string $nextReviewOn): CardProgress
    {
        return new CardProgress(['box' => $box, 'next_review_on' => $nextReviewOn]);
    }

    private function answer(string $answeredAt, string $dueOn, bool $known, ?AnswerStatus $status = null, ?int $fromBox = null, ?int $toBox = null): ReviewAnswer
    {
        return new ReviewAnswer([
            'answered_at' => CarbonImmutable::parse($answeredAt)->utc(),
            'due_on' => $dueOn,
            'known' => $known,
            'status' => $status,
            'from_box' => $fromBox,
            'to_box' => $toBox,
        ]);
    }

    private function replay(string $timezone, string $now): CardAnswerReplay
    {
        return new CardAnswerReplay($timezone, CarbonImmutable::parse($now)->utc());
    }

    /**
     * @return array{int, string, string|null}
     */
    private function stateOf(CardProgress $card): array
    {
        return [$card->box, $card->next_review_on->toDateString(), $card->last_answered_at?->toIso8601String()];
    }

    /**
     * @return array{AnswerStatus, int, int}
     */
    private function outcomeOf(ReviewAnswer $answer): array
    {
        return [$answer->status, $answer->from_box, $answer->to_box];
    }

    /**
     * @return array<string, array{int, bool, int, string}>
     */
    public static function leitnerTransitions(): array
    {
        return [
            'box 1, known' => [1, true, 2, '2026-10-07'],
            'box 2, known' => [2, true, 3, '2026-10-09'],
            'box 3, known' => [3, true, 4, '2026-10-13'],
            'box 4, known' => [4, true, 5, '2026-10-21'],
            'box 5, known' => [5, true, 5, '2026-10-21'],
            'box 1, missed' => [1, false, 1, '2026-10-06'],
            'box 2, missed' => [2, false, 1, '2026-10-06'],
            'box 3, missed' => [3, false, 1, '2026-10-06'],
            'box 4, missed' => [4, false, 1, '2026-10-06'],
            'box 5, missed' => [5, false, 1, '2026-10-06'],
        ];
    }

    #[DataProvider('leitnerTransitions')]
    public function test_an_answer_on_its_due_date_follows_the_leitner_table(int $box, bool $known, int $arrivalBox, string $nextReviewOn): void
    {
        $card = $this->card($box, '2026-10-05');
        $incoming = $this->answer('2026-10-05T10:00:00+02:00', '2026-10-05', $known);

        $this->replay('Europe/Paris', '2026-10-07T18:00:00+02:00')->replay($card, $incoming, collect());

        $this->assertSame([AnswerStatus::Applied, $box, $arrivalBox], $this->outcomeOf($incoming));
        $this->assertSame([$arrivalBox, $nextReviewOn, '2026-10-05T08:00:00+00:00'], $this->stateOf($card));
    }

    public function test_an_earlier_answer_rewinds_the_card_to_before_the_oldest_later_applied_answer(): void
    {
        $card = $this->card(3, '2026-10-10');
        $noon = $this->answer('2026-10-06T12:00:00+02:00', '2026-10-06', true, AnswerStatus::Applied, 2, 3);
        $morning = $this->answer('2026-10-06T08:00:00+02:00', '2026-10-06', false);

        $this->replay('Europe/Paris', '2026-10-06T18:00:00+02:00')->replay($card, $morning, collect([$noon]));

        $this->assertSame([AnswerStatus::Applied, 2, 1], $this->outcomeOf($morning));
        $this->assertSame([AnswerStatus::Discarded, 1, 1], $this->outcomeOf($noon));
        $this->assertSame([1, '2026-10-07', '2026-10-06T06:00:00+00:00'], $this->stateOf($card));
    }

    public function test_the_later_answers_are_replayed_in_the_order_they_were_given(): void
    {
        $card = $this->card(1, '2026-10-05');
        $wednesday = $this->answer('2026-10-07T09:00:00+02:00', '2026-10-07', true, AnswerStatus::Discarded, 1, 1);
        $sunday = $this->answer('2026-10-11T09:00:00+02:00', '2026-10-11', true, AnswerStatus::Discarded, 1, 1);
        $monday = $this->answer('2026-10-05T09:00:00+02:00', '2026-10-05', true);

        $this->replay('Europe/Paris', '2026-10-12T18:00:00+02:00')->replay($card, $monday, collect([$wednesday, $sunday]));

        $this->assertSame([AnswerStatus::Applied, 1, 2], $this->outcomeOf($monday));
        $this->assertSame([AnswerStatus::Applied, 2, 3], $this->outcomeOf($wednesday));
        $this->assertSame([AnswerStatus::Applied, 3, 4], $this->outcomeOf($sunday));
        $this->assertSame([4, '2026-10-19', '2026-10-11T07:00:00+00:00'], $this->stateOf($card));
    }

    public function test_an_answer_to_another_due_date_is_discarded(): void
    {
        $card = $this->card(2, '2026-10-05');
        $card->last_answered_at = CarbonImmutable::parse('2026-10-03T09:00:00+00:00');
        $incoming = $this->answer('2026-10-05T10:00:00+02:00', '2026-10-04', true);

        $this->replay('Europe/Paris', '2026-10-05T18:00:00+02:00')->replay($card, $incoming, collect());

        $this->assertSame([AnswerStatus::Discarded, 2, 2], $this->outcomeOf($incoming));
        $this->assertSame([2, '2026-10-05', '2026-10-03T09:00:00+00:00'], $this->stateOf($card));
    }

    public function test_an_answer_given_before_its_due_date_counts_when_received_if_the_card_is_due_then(): void
    {
        $card = $this->card(2, '2026-10-08');
        $incoming = $this->answer('2026-10-06T20:00:00+02:00', '2026-10-08', true);

        $this->replay('Europe/Paris', '2026-10-08T12:00:00+02:00')->replay($card, $incoming, collect());

        $this->assertSame([AnswerStatus::Applied, 2, 3], $this->outcomeOf($incoming));
        $this->assertSame('2026-10-08T10:00:00+00:00', $incoming->answered_at->toIso8601String());
        $this->assertSame([3, '2026-10-12', '2026-10-08T10:00:00+00:00'], $this->stateOf($card));
    }

    public function test_an_answer_given_before_its_due_date_is_discarded_if_the_card_is_not_due_when_received(): void
    {
        $card = $this->card(2, '2026-10-08');
        $incoming = $this->answer('2026-10-06T20:00:00+02:00', '2026-10-08', true);

        $this->replay('Europe/Paris', '2026-10-07T12:00:00+02:00')->replay($card, $incoming, collect());

        $this->assertSame([AnswerStatus::Discarded, 2, 2], $this->outcomeOf($incoming));
        $this->assertSame('2026-10-07T10:00:00+00:00', $incoming->answered_at->toIso8601String());
        $this->assertSame([2, '2026-10-08', null], $this->stateOf($card));
    }

    /**
     * @return array<string, array{string, AnswerStatus}>
     */
    public static function timezones(): array
    {
        return [
            'already the 6th in Tokyo' => ['Asia/Tokyo', AnswerStatus::Applied],
            'still the 5th in Montreal' => ['America/Montreal', AnswerStatus::Discarded],
        ];
    }

    #[DataProvider('timezones')]
    public function test_the_day_of_an_answer_is_that_of_the_account_time_zone(string $timezone, AnswerStatus $status): void
    {
        $card = $this->card(1, '2026-10-06');
        $incoming = $this->answer('2026-10-05T23:30:00+00:00', '2026-10-06', true);

        $this->replay($timezone, '2026-10-05T23:45:00+00:00')->replay($card, $incoming, collect());

        $this->assertSame($status, $incoming->status);
    }
}
