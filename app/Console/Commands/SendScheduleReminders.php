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

        $due = Schedule::with(['creator', 'assignee'])
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            // Both dates, so a window that crosses midnight still works.
            ->whereBetween('scheduled_date', [$now->toDateString(), $until->toDateString()])
            ->get()
            ->filter(function (Schedule $schedule) use ($now, $until, $timezone) {
                $start = $this->startOf($schedule, $timezone);

                return $start->gt($now) && $start->lte($until);
            });

        $sent = 0;

        foreach ($due as $schedule) {
            // Claim it first, so two overlapping runs can never send it twice.
            $claimed = Schedule::whereKey($schedule->id)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => now()]);

            if (!$claimed) {
                continue;
            }

            // Someone it was assigned to, otherwise the person who made it.
            $recipient = $schedule->assignee ?? $schedule->creator;

            if (!$recipient) {
                continue;
            }

            $secondsLeft = $this->startOf($schedule, $timezone)->getTimestamp() - $now->getTimestamp();
            $minutesLeft = max(1, (int) ceil($secondsLeft / 60));

            try {
                $recipient->notify(new ScheduleReminder($schedule, $minutesLeft));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Could not send reminder for schedule {$schedule->id}: " . $e->getMessage());
            }
        }

        $this->info("Sent {$sent} reminder(s).");

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
