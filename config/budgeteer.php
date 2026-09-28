<?php

return [

    // Used by `php artisan budgeteer:setup` on a new install.
    'household_name' => env('BUDGETEER_HOUSEHOLD_NAME', 'My household'),
    'allowed_emails' => env('BUDGETEER_ALLOWED_EMAILS', ''),

    // Sign in as any user without Google, for development on this machine only.
    'dev_login' => (bool) env('BUDGETEER_DEV_LOGIN', false),

];
