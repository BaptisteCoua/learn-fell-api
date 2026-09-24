<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Models\Category;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\Relation;

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
