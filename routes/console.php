<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('hookrelay:sweep')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('hookrelay:prune')->dailyAt('03:30')->onOneServer();
