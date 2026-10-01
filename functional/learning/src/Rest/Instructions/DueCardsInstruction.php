<?php

namespace Functional\Learning\Rest\Instructions;

use Functional\Learning\Queries\DueCardsQuery;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

/**
 * The cards to review today of the chosen subjects (DueCardsQuery), the most overdue first,
 * then in the subject's question order.
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
        DueCardsQuery::inReviewOrder(DueCardsQuery::constrain($query, Auth::user()->timezone, $fields['subject_ids']));
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
