<?php

namespace App\Notifications;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ScheduleReminder extends Notification
{
    public function __construct(public Schedule $schedule, public int $minutesLeft)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $start = $this->clock($this->schedule->start_time);
        $end = $this->clock($this->schedule->end_time);

        $when = $this->minutesLeft <= 1
            ? 'Starting in 1 minute'
            : "Starting in {$this->minutesLeft} minutes";

        return (new WebPushMessage)
            ->title($when)
            ->body("{$this->schedule->title} · {$start} – {$end}")
            ->icon('/favicon.ico')
            ->tag('schedule-reminder-' . $this->schedule->id)
            ->requireInteraction()
            ->data([
                'url' => '/',
                'schedule_id' => $this->schedule->id,
            ]);
    }

    private function clock(?string $time): string
    {
        return Carbon::createFromFormat('H:i', substr((string) $time, 0, 5))->format('g:i A');
    }
}   
