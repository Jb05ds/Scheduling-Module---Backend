<?php

return [

    'timezone' => env('SCHEDULE_TIMEZONE', 'Asia/Manila'),

    'reminder_minutes' => (int) env('SCHEDULE_REMINDER_MINUTES', 10),

    'cron_secret' => env('CRON_SECRET'),

];
