<?php

namespace Functional\Moderation\Rest\Controllers;

use Functional\Moderation\Rest\Resources\ReportResource;
use Lomkit\Rest\Http\Controllers\Controller;

class ReportsController extends Controller
{
    public static $resource = ReportResource::class;
}
