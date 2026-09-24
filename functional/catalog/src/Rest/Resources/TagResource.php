<?php

namespace Functional\Catalog\Rest\Resources;

use Functional\Catalog\Models\Tag;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class TagResource extends Resource
{
    public static $model = Tag::class;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'name'];
    }

    /**
     * @return array<string, string>
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return ['name' => 'asc'];
    }
}
