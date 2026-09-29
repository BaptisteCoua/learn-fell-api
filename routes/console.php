<?php

use Functional\Catalog\Models\QuestionImage;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();

// A pending question image does not outlive 24 hours (003, FR-018): the daily run would keep it 48.
Schedule::command('model:prune', ['--model' => [QuestionImage::class]])->hourly();

// Reminders leave within the minute of the chosen time (002, SC-002).
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping()->onOneServer();
