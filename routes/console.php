<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Push a "starting soon" reminder shortly before each schedule begins.
Schedule::command('schedules:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();
