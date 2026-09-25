<?php

namespace Functional\Catalog\Rest\Controllers;

use Functional\Catalog\Rest\Resources\TagResource;
use Lomkit\Rest\Http\Controllers\Controller;

class TagsController extends Controller
{
    public static $resource = TagResource::class;
}
