<?php

namespace Functional\Learning\Rest\Instructions;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Functional\Learning\Domain\LeitnerSchedule;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

/**
 * The cards to review today (FR-044): of the chosen subjects, still published, due today in
 * the learner's time zone, the most overdue first, then in the subject's question order.
 * An unpublished subject pauses its cards without losing them (research R8).
 */
class DueCardsInstruction extends Instruction
{
    public function uriKey(): string
    {
        return 'due';
    }

    /**
     * @param  array{subject_ids: list<int>}  $fields
     */
    public function handle(array $fields, Builder $query): void
    {
        $today = LeitnerSchedule::todayFor(Auth::user()->timezone)->toDateString();

        $query
            ->whereIn('card_progress.subject_id', $fields['subject_ids'])
            ->where('card_progress.next_review_on', '<=', $today)
            ->whereHas('subject', fn (Builder $subjects): Builder => $subjects->where('status', SubjectStatus::Published))
            ->reorder()
            ->orderBy('card_progress.next_review_on')
            ->orderBy('card_progress.subject_id')
            ->orderBy(Question::query()->select('position')->whereColumn('questions.id', 'card_progress.question_id'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'subject_ids' => ['required', 'array', 'max:100'],
            'subject_ids.*' => ['integer'],
        ];
    }
}
