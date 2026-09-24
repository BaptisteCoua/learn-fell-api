<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Question;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\Relation;

class QuestionResource extends Resource
{
    public static $model = Question::class;

    public int $defaultLimit = 50;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'subject_id', 'recto_html', 'verso_html', 'position'];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [BelongsTo::make('subject', SubjectResource::class)];
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
        return ['position' => 'asc'];
    }

    /**
     * A question follows its subject: published for visitors, QuestionControl otherwise.
     */
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $request->user() === null
            ? $query->whereHas('subject', fn (Builder $subjects): Builder => $subjects->where('status', SubjectStatus::Published))
            : $query->controlled();
    }
}
