<?php

namespace Functional\Users\Rest\Resources;

use Functional\Users\Models\User;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

/**
 * The public face of an account: its display name, never its email (FR-026).
 */
class PublicUserResource extends Resource
{
    public static $model = User::class;

    /**
     * @return list<string>
     */
    public function fields(RestRequest $request): array
    {
        return ['id', 'display_name'];
    }
}
