<?php

namespace Functional\Moderation\Rest\Controllers;

use Functional\Moderation\Rest\Resources\ModerationDecisionResource;
use Lomkit\Rest\Http\Controllers\Controller;

class ModerationDecisionsController extends Controller
{
    public static $resource = ModerationDecisionResource::class;
}
