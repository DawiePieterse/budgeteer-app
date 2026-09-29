<?php

use Illuminate\Support\Facades\Schedule;

// cPanel cron runs `php artisan schedule:run` every 5 minutes.
Schedule::command('budgeteer:gmail-sync')->everyFiveMinutes()->withoutOverlapping(10);

// Phone notifications only in the day; anything that happens at night is sent at 07:00.
Schedule::command('budgeteer:notify')->everyFifteenMinutes()->between('7:00', '20:30')->withoutOverlapping(10);
