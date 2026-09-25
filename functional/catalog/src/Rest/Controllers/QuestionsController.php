<?php

namespace Functional\Catalog\Rest\Controllers;

use Functional\Catalog\Rest\Resources\QuestionResource;
use Lomkit\Rest\Http\Controllers\Controller;

class QuestionsController extends Controller
{
    public static $resource = QuestionResource::class;
}
