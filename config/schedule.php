<?php

return [

    /*
    | Schedule dates and times are stored as plain wall-clock values (no timezone),
    | so reminders compare them against the clock in this timezone.
    */
    'timezone' => env('SCHEDULE_TIMEZONE', 'Asia/Manila'),

    /*
    | How many minutes before the start time the reminder is sent.
    */
    'reminder_minutes' => (int) env('SCHEDULE_REMINDER_MINUTES', 10),

];
