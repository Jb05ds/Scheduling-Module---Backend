<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$reminders = Schedule::command('schedules:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();

if (is_writable('/proc/1/fd/1')) {
    $reminders->appendOutputTo('/proc/1/fd/1');
}