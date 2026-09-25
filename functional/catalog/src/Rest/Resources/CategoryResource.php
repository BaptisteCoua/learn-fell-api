<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Models\Category;
use Functional\Catalog\Rest\Actions\ReorderCategories;
use Functional\Catalog\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\Relation;
use Technical\Osdd\Exceptions\BusinessRuleException;

class CategoryResource extends Resource
{
    public static $model = Category::class;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'name', 'position'];
    }

    /**
     * @return list<Relation>
     */
    public function relations(RestRequest $request): array
    {
        return [HasMany::make('subjects', SubjectResource::class)];
    }

    /**
     * Writes need `categories.manage` (CategoryControl); the position is set by the API and
     * the `reorder` action.
     *
     * @return array<string, mixed>
     */
    public function rules(RestRequest $request): array
    {
        return [
            'name' => ['string', 'max:60'],
            'id' => ['prohibited'],
            'position' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createRules(RestRequest $request): array
    {
        return ['name' => ['required']];
    }

    /**
     * Two categories never share a name, ignoring case and accents (FR-009); a new one goes last.
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function mutating(MutateRequest $request, array $requestBody, Model $model): void
    {
        $name = $requestBody['attributes']['name'] ?? null;

        if ($name !== null) {
            $isTaken = Category::query()
                ->where('name_normalized', TextNormalizer::normalize($name))
                ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($isTaken) {
                throw new BusinessRuleException('category_name_taken', replace: ['name' => $name]);
            }
        }

        if (! $model->exists) {
            $model->position = (int) Category::query()->max('position') + 1;
        }
    }

    /**
     * A category holding subjects, whatever their status, stays (FR-010).
     */
    public function destroying(DestroyRequest $request, Model $model): void
    {
        $subjectCount = $model->subjects()->count();

        if ($subjectCount > 0) {
            throw new BusinessRuleException(
                'category_not_empty',
                replace: ['name' => $model->name, 'count' => $subjectCount],
                context: ['subjects_count' => $subjectCount],
            );
        }
    }

    /**
     * @return list<Action>
     */
    public function actions(RestRequest $request): array
    {
        return [ReorderCategories::make()->standalone()];
    }

    /**
     * @return list<int>
     */
    public function limits(RestRequest $request): array
    {
        return [1, 50, 100];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['position' => 'asc'];
    }
}
