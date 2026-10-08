<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function sendReminders(Request $request)
    {
        $secret = (string) config('schedule.cron_secret');
        $given = (string) ($request->header('X-Cron-Token') ?? $request->query('token'));

        abort_unless($secret !== '' && hash_equals($secret, $given), 403, 'Forbidden.');

        Artisan::call('schedules:send-reminders');

        return response()->json([
            'message' => trim(Artisan::output()),
        ]);
    }
}
