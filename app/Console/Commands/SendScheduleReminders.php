<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Notifications\ScheduleReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduleReminders extends Command
{
    protected $signature = 'schedules:send-reminders';

    protected $description = 'Send a push notification shortly before a schedule starts';

    public function handle(): int
    {
        $timezone = config('schedule.timezone');
        $minutes = (int) config('schedule.reminder_minutes');

        $now = Carbon::now($timezone);
        $until = $now->copy()->addMinutes($minutes);

        $candidates = Schedule::with(['creator', 'assignee'])
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_date', [$now->toDateString(), $until->toDateString()])
            ->get();

        $due = $candidates->filter(function (Schedule $schedule) use ($now, $until, $timezone) {
            $start = $this->startOf($schedule, $timezone);

            return $start->gt($now) && $start->lte($until);
        });

        $sent = 0;

        foreach ($due as $schedule) {
            $recipient = $schedule->assignee ?? $schedule->creator;

            if (!$recipient) {
                continue;
            }

            $secondsLeft = $this->startOf($schedule, $timezone)->getTimestamp() - $now->getTimestamp();
            $minutesLeft = max(1, (int) ceil($secondsLeft / 60));

            try {
                $recipient->notify(new ScheduleReminder($schedule, $minutesLeft));

                $claimed = Schedule::whereKey($schedule->id)
                    ->whereNull('reminder_sent_at')
                    ->update(['reminder_sent_at' => now()]);

                if ($claimed) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                $this->error("Could not send reminder for schedule {$schedule->id}: {$e->getMessage()}");
                Log::error("Could not send reminder for schedule {$schedule->id}", [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        $this->info("[{$now->toDateTimeString()} {$timezone}] candidates: {$candidates->count()}, due: {$due->count()}, sent: {$sent}");

        return self::SUCCESS;
    }

    private function startOf(Schedule $schedule, string $timezone): Carbon
    {
        return Carbon::parse(
            substr((string) $schedule->scheduled_date, 0, 10) . ' ' . $schedule->start_time,
            $timezone,
        );
    }
}