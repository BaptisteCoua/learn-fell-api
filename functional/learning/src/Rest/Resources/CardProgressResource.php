<?php

namespace Functional\Learning\Rest\Resources;

use Functional\Catalog\Rest\Resources\QuestionResource;
use Functional\Catalog\Rest\Resources\SubjectResource;
use Functional\Learning\Models\CardProgress;
use Functional\Learning\Rest\Actions\AnswerCard;
use Functional\Learning\Rest\Instructions\DueCardsInstruction;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\Relation;

class CardProgressResource extends Resource
{
    public static $model = CardProgress::class;

    public int $defaultLimit = 100;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'subject_id', 'question_id', 'box', 'next_review_on', 'last_answered_at'];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('question', QuestionResource::class),
            BelongsTo::make('subject', SubjectResource::class),
        ];
    }

    /**
     * @return list<Instruction>
     */
    public function instructions(RestRequest $request): array
    {
        return [DueCardsInstruction::make()];
    }

    /**
     * @return list<Action>
     */
    public function actions(RestRequest $request): array
    {
        return [AnswerCard::make()->standalone()];
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 10, 25, 50, 100];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['next_review_on' => 'asc', 'id' => 'asc'];
    }

    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $query->controlled();
    }
}
