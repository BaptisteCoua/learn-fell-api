<?php

namespace Functional\Reminders\Rest\Controllers;

use Functional\Reminders\Rest\Resources\PushSubscriptionResource;
use Lomkit\Rest\Http\Controllers\Controller;

class PushSubscriptionsController extends Controller
{
    public static $resource = PushSubscriptionResource::class;
}
