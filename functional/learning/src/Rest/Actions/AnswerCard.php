<?php

namespace Functional\Learning\Rest\Actions;

use Carbon\CarbonImmutable;
use Functional\Learning\Domain\CardAnswerReplay;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Models\ReviewAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

/**
 * "Je savais" or "je ne savais pas" on a card (FR-046, FR-048), given online or offline and sent
 * later (feature 006). The answer counts when it was given, through the replay of its card. An
 * answer already received, or on a card gone, paused or someone else's, changes nothing and is
 * answered alike, so that it reveals nothing (FR-014, FR-016).
 */
class AnswerCard extends Action
{
    public function uriKey(): string
    {
        return 'answer';
    }

    /**
     * @param  array{card_progress_id: int, known: bool, answer_id?: string, answered_at?: string, due_on?: string}  $fields
     * @param  Collection<int, CardProgress>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        $learner = Auth::user();
        $now = CarbonImmutable::now()->setTimezone(config('app.timezone'));

        DB::transaction(function () use ($fields, $learner, $now): void {
            $card = CardProgress::query()
                ->with('subject')
                ->where('user_id', $learner->getKey())
                ->lockForUpdate()
                ->find($fields['card_progress_id']);

            if ($card === null || ! $card->subject->status->isPublic()) {
                return;
            }

            $answerId = $fields['answer_id'] ?? (string) Str::uuid();

            if (ReviewAnswer::query()->where('answer_id', $answerId)->exists()) {
                return;
            }

            $incoming = new ReviewAnswer([
                'answer_id' => $answerId,
                'card_progress_id' => $card->getKey(),
                'user_id' => $learner->getKey(),
                'known' => (bool) $fields['known'],
                'answered_at' => isset($fields['answered_at'])
                    ? CarbonImmutable::parse($fields['answered_at'])->setTimezone(config('app.timezone'))
                    : $now,
                'due_on' => $fields['due_on'] ?? $card->next_review_on->toDateString(),
            ]);

            (new CardAnswerReplay($learner->timezone, $now))->record($card, $incoming);
        });
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'answer_id' => ['sometimes', 'uuid'],
            'card_progress_id' => ['required', 'integer'],
            'known' => ['required', 'boolean'],
            'answered_at' => ['sometimes', 'date'],
            'due_on' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }
}
