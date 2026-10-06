<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'title',
        'description',
        'scheduled_date',
        'start_time',
        'end_time',
        'status',
        'reminder_sent_at',
        'created_by',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
