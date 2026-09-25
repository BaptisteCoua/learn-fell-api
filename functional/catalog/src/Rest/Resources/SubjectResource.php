<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Models\Subject;
use Functional\Catalog\Rest\Actions\PublishSubject;
use Functional\Catalog\Rest\Actions\SyncSubjectTags;
use Functional\Catalog\Rest\Actions\UnpublishSubject;
use Functional\Catalog\Rest\Instructions\SearchInstruction;
use Functional\Catalog\Support\RetiredSubjectLock;
use Functional\Users\Rest\Resources\PublicUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\MutateRequest;
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

    public const SERVER_FIELDS = [
        'id', 'status', 'author_id', 'published_at', 'retired_reason', 'retired_at', 'created_at', 'updated_at',
    ];

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
     * Rules of every write (FR-012). Status, author and dates are set by the API, never sent.
     *
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'title' => ['string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['integer', Rule::exists('categories', 'id')],
            ...array_fill_keys(self::SERVER_FIELDS, ['prohibited']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRules(RestRequest $request): array
    {
        return [
            'title' => ['required'],
            'category_id' => ['required'],
        ];
    }

    /**
     * A new subject is a draft of its author; a retired one is locked for its author.
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        if ($model->exists) {
            RetiredSubjectLock::ensureEditable($model);
        } else {
            $model->author_id = $request->user()->getKey();
        }
    }

    /**
     * @return list<Action>
     */
    public function actions(RestRequest $request): array
    {
        return [PublishSubject::make(), UnpublishSubject::make(), SyncSubjectTags::make()];
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
        return [1, 10, 20, 50];
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
