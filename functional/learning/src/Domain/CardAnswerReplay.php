<?php

namespace Functional\Learning\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Functional\Learning\Enums\AnswerStatus;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Illuminate\Support\Collection;

/**
 * Applies an answer to its card as if the card had received its answers in the order they were
 * given (feature 006, research R3 and R4): the card goes back to where it stood when the answer
 * was given, then the answer and every later one are replayed. A due date takes one answer, the
 * first given; the others are discarded.
 */
final class CardAnswerReplay
{
    /**
     * @param  string  $timezone  the learner's, in which an answer falls on a day
     * @param  CarbonImmutable  $now  when the answer is received
     */
    public function __construct(
        private readonly string $timezone,
        private readonly CarbonImmutable $now,
    ) {}

    /**
     * Records the incoming answer on the card, locked by the caller, and saves the replayed card
     * and answers.
     */
    public function record(CardProgress $card, ReviewAnswer $incoming): void
    {
        if ($incoming->answered_at->greaterThan($this->now)) {
            $incoming->answered_at = $this->now;
        }

        $laterAnswers = $card->answers()
            ->where('answered_at', '>', $incoming->answered_at)
            ->orderBy('answered_at')
            ->orderBy('id')
            ->get();

        $this->replay($card, $incoming, $laterAnswers);

        $incoming->save();
        $laterAnswers->each(fn (ReviewAnswer $answer): bool => $answer->save());
        $card->save();
    }

    /**
     * Replays, in memory, the incoming answer then the answers given after it. A discarded answer
     * left the card as it was, so the card stood before the incoming answer where it stood
     * before the oldest later applied answer, or where it stands now if there is none.
     *
     * @param  Collection<int, ReviewAnswer>  $laterAnswers  given after the incoming answer, in order
     */
    public function replay(CardProgress $card, ReviewAnswer $incoming, Collection $laterAnswers): void
    {
        $oldestLaterApplied = $laterAnswers->first(fn (ReviewAnswer $answer): bool => $answer->status === AnswerStatus::Applied);

        if ($oldestLaterApplied !== null) {
            $card->box = $oldestLaterApplied->from_box;
            $card->next_review_on = $oldestLaterApplied->due_on;
        }

        $answerFromLateClock = null;

        foreach (collect([$incoming])->concat($laterAnswers) as $answer) {
            if ($this->answersCurrentDueDate($card, $answer)) {
                $this->apply($card, $answer);
            } elseif ($answer === $incoming && $this->answersDueDateNotYetReached($card, $answer)) {
                $answerFromLateClock = $answer;
            } else {
                $this->discard($card, $answer);
            }
        }

        if ($answerFromLateClock !== null) {
            $answerFromLateClock->answered_at = $this->now;

            if ($this->answersCurrentDueDate($card, $answerFromLateClock)) {
                $this->apply($card, $answerFromLateClock);
            } else {
                $this->discard($card, $answerFromLateClock);
            }
        }
    }

    private function answersCurrentDueDate(CardProgress $card, ReviewAnswer $answer): bool
    {
        return $this->isCurrentDueDate($card, $answer)
            && $card->next_review_on->toDateString() <= $this->dayOf($answer->answered_at)->toDateString();
    }

    /**
     * The device's clock was running late: the answer is for the current due date, but was
     * dated before it.
     */
    private function answersDueDateNotYetReached(CardProgress $card, ReviewAnswer $answer): bool
    {
        return $this->isCurrentDueDate($card, $answer)
            && $card->next_review_on->toDateString() > $this->dayOf($answer->answered_at)->toDateString();
    }

    private function isCurrentDueDate(CardProgress $card, ReviewAnswer $answer): bool
    {
        return $card->next_review_on->toDateString() === $answer->due_on->toDateString();
    }

    private function apply(CardProgress $card, ReviewAnswer $answer): void
    {
        $arrivalBox = LeitnerSchedule::arrivalBox($card->box, $answer->known);

        $answer->fill(['from_box' => $card->box, 'to_box' => $arrivalBox, 'status' => AnswerStatus::Applied]);
        $card->fill([
            'box' => $arrivalBox,
            'next_review_on' => LeitnerSchedule::nextReviewOn($arrivalBox, $this->dayOf($answer->answered_at))->toDateString(),
            'last_answered_at' => $answer->answered_at,
        ]);
    }

    private function discard(CardProgress $card, ReviewAnswer $answer): void
    {
        $answer->fill(['from_box' => $card->box, 'to_box' => $card->box, 'status' => AnswerStatus::Discarded]);
    }

    private function dayOf(CarbonInterface $instant): CarbonImmutable
    {
        return CarbonImmutable::instance($instant)->setTimezone($this->timezone)->startOfDay();
    }
}
