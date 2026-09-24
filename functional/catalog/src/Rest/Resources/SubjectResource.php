<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Rest\Instructions\SearchInstruction;
use Functional\Users\Rest\Resources\PublicUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\Relation;

class SubjectResource extends Resource
{
    public static $model = Subject::class;

    public int $defaultLimit = 20;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return [
            'id', 'title', 'description', 'status', 'category_id', 'author_id',
            'published_at', 'retired_reason', 'retired_at', 'created_at', 'updated_at',
        ];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('author', PublicUserResource::class),
            BelongsTo::make('category', CategoryResource::class),
            BelongsToMany::make('tags', TagResource::class),
            HasMany::make('questions', QuestionResource::class),
        ];
    }

    /**
     * @return list<Instruction>
     */
    public function instructions(RestRequest $request): array
    {
        return [SearchInstruction::make()];
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [10, 20, 50];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['published_at' => 'desc', 'id' => 'desc'];
    }

    /**
     * Visitors only ever reach published subjects; signed-in users go through SubjectControl.
     */
    public function searchQuery(RestRequest $request, Builder $query): Builder
    {
        return $request->user() === null
            ? $query->where('status', SubjectStatus::Published)
            : $query->controlled();
    }
}
