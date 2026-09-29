<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();

// Reminders leave within the minute of the chosen time (002, SC-002).
Schedule::command('reminders:dispatch')->everyMinute()->withoutOverlapping()->onOneServer();
