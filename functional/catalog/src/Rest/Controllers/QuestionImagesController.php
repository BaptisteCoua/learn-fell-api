<?php

namespace Functional\Catalog\Rest\Controllers;

use Functional\Catalog\Rest\Resources\QuestionImageResource;
use Illuminate\Auth\Access\AuthorizationException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;

/**
 * Read only: an image changes through the mutate of its question (contracts/api.md §3).
 */
class QuestionImagesController extends Controller
{
    public static $resource = QuestionImageResource::class;

    protected function beforeMutate(MutateRequest $request): void
    {
        throw new AuthorizationException;
    }

    protected function beforeOperate(OperateRequest $request): void
    {
        throw new AuthorizationException;
    }
}
