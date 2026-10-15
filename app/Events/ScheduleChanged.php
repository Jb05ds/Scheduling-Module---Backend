<?php

namespace App\Events;

use App\Models\Schedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Schedule $schedule
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'user.' . $this->schedule->assigned_to
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'schedule.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->schedule->id,
            'title' => $this->schedule->title,
            'scheduled_date' => $this->schedule->scheduled_date,
            'start_time' => $this->schedule->start_time,
            'end_time' => $this->schedule->end_time,
            'status' => $this->schedule->status,
            'assigned_to' => $this->schedule->assigned_to,
        ];
    }
}
