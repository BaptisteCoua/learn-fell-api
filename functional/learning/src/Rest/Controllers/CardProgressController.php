<?php

namespace Functional\Learning\Rest\Controllers;

use Functional\Learning\Rest\Resources\CardProgressResource;
use Lomkit\Rest\Http\Controllers\Controller;

class CardProgressController extends Controller
{
    public static $resource = CardProgressResource::class;
}
