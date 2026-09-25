<?php

namespace Functional\Catalog\Rest\Controllers;

use Functional\Catalog\Rest\Resources\SubjectResource;
use Lomkit\Rest\Http\Controllers\Controller;

class SubjectsController extends Controller
{
    public static $resource = SubjectResource::class;
}
