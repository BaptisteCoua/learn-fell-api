<?php

namespace Functional\Learning\Rest\Controllers;

use Functional\Learning\Rest\Resources\LearningResource;
use Lomkit\Rest\Http\Controllers\Controller;

class LearningsController extends Controller
{
    public static $resource = LearningResource::class;
}
