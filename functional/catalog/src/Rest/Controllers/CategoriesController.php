<?php

namespace Functional\Catalog\Rest\Controllers;

use Functional\Catalog\Rest\Resources\CategoryResource;
use Lomkit\Rest\Http\Controllers\Controller;

class CategoriesController extends Controller
{
    public static $resource = CategoryResource::class;
}
