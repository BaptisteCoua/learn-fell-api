<?php

namespace Functional\Learning\Rest\Actions;

use Functional\Learning\Domain\LeitnerSchedule;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * "Je savais" or "je ne savais pas" on a due card (FR-046, FR-048). Once answered the card is
 * due later, so a second answer — a double click, a replayed request — is refused.
 */
class AnswerCard extends Action
{
    public function uriKey(): string
    {
        return 'answer';
    }

    /**
     * @param  array{card_progress_id: int, known: bool}  $fields
     * @param  Collection<int, CardProgress>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $learner = Auth::user();

        DB::transaction(function () use ($fields, $learner): void {
            $card = CardProgress::query()
                ->with('subject')
                ->where('user_id', $learner->getKey())
                ->lockForUpdate()
                ->findOrFail($fields['card_progress_id']);

            $today = LeitnerSchedule::todayFor($learner->timezone);

            if ($card->next_review_on->toDateString() > $today->toDateString() || ! $card->subject->status->isPublic()) {
                throw new BusinessRuleException('card_not_due', 409);
            }

            $known = (bool) $fields['known'];
            $toBox = LeitnerSchedule::arrivalBox($card->box, $known);

            ReviewAnswer::query()->create([
                'card_progress_id' => $card->getKey(),
                'user_id' => $learner->getKey(),
                'known' => $known,
                'from_box' => $card->box,
                'to_box' => $toBox,
                'answered_at' => now(),
            ]);

            $card->update([
                'box' => $toBox,
                'next_review_on' => LeitnerSchedule::nextReviewOn($toBox, $today)->toDateString(),
                'last_answered_at' => now(),
            ]);
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'card_progress_id' => ['required', 'integer'],
            'known' => ['required', 'boolean'],
        ];
    }
}
