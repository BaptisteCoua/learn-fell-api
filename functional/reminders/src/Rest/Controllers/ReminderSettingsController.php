<?php

namespace Functional\Reminders\Rest\Controllers;

use Functional\Reminders\Rest\Resources\ReminderSettingResource;
use Lomkit\Rest\Http\Controllers\Controller;

class ReminderSettingsController extends Controller
{
    public static $resource = ReminderSettingResource::class;
}
