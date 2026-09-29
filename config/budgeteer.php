<?php

return [

    // Used by `php artisan budgeteer:setup` on a new install.
    'household_name' => env('BUDGETEER_HOUSEHOLD_NAME', 'My household'),
    'allowed_emails' => env('BUDGETEER_ALLOWED_EMAILS', ''),

    // Sign in as any user without Google, for development on this machine only.
    'dev_login' => (bool) env('BUDGETEER_DEV_LOGIN', false),

    // Phone notifications (Web Push). Create the keys once with `php artisan budgeteer:push-keys`.
    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:'.env('MAIL_FROM_ADDRESS', 'budget@example.com')),
    ],

];
