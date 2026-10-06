<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ScheduleAssigned extends Notification
{
    public function __construct(public Schedule $schedule)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $schedule = $this->schedule->loadMissing('creator');

        $date = $schedule->scheduled_date instanceof \DateTimeInterface
            ? $schedule->scheduled_date->format('M j, Y')
            : (string) $schedule->scheduled_date;

        $start = substr((string) $schedule->start_time, 0, 5);
        $end = substr((string) $schedule->end_time, 0, 5);

        return (new WebPushMessage)
            ->title('New schedule from ' . ($schedule->creator?->name ?? 'someone'))
            ->body("{$schedule->title} · {$date}, {$start}–{$end}")
            ->icon('/favicon.ico')
            // Same tag = a newer notification for the same schedule replaces the old one.
            ->tag('schedule-' . $schedule->id)
            ->data([
                'url' => '/',
                'schedule_id' => $schedule->id,
            ]);
    }
}
