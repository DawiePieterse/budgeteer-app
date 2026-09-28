<?php

use Illuminate\Support\Facades\Schedule;

// cPanel cron runs `php artisan schedule:run` every 5 minutes.
Schedule::command('budgeteer:gmail-sync')->everyFiveMinutes()->withoutOverlapping(10);
