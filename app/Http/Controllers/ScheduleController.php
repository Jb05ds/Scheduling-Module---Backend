<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
            'scheduled_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Schedule::with(['creator', 'assignee']);

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['scheduled_date'])) {
            $query->where('scheduled_date', $validated['scheduled_date']);
        }

        if (!empty($validated['assigned_to'])) {
            $query->where('assigned_to', $validated['assigned_to']);
        }

        if (!empty($validated['search'])) {
            $query->where('title', 'like', '%' . $validated['search'] . '%');
        }

        $schedules = $query->get();

        return response()->json([
            'data' => $schedules,
        ]);
    }
    public function store(StoreScheduleRequest $request)
    {
        $validated = $request->validated();

        $conflict = Schedule::where('scheduled_date', $validated['scheduled_date'])
            ->where('assigned_to', $validated['assigned_to'] ?? null)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($validated) {
                $query->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'The assigned user already has a schedule during this time.',
            ]);
        }

        $schedule = Schedule::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => $request->user()->getAuthIdentifier(),  
            'status' => 'scheduled',
        ]);

        return response()->json([
            'message' => 'Schedule created successfully.',
            'data' => $schedule,
        ], 201);
    }

    public function show(Schedule $schedule)
    {
        $schedule->load(['creator', 'assignee']);

        return response()->json([
            'data' => $schedule
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule)
    {
        $validated = $request->validated();

        $conflict = Schedule::where('scheduled_date', $validated['scheduled_date'])
            ->where('assigned_to', $validated['assigned_to'] ?? null)
            ->where('status', '!=', 'cancelled')
            ->where('id', '!=', $schedule->id)
            ->where(function ($query) use ($validated) {
                $query->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'The assigned user already has a schedule during this time.',
            ]);
        }

        $schedule->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'status' => $validated['status'] ?? $schedule->status,
        ]);

        return response()->json([
            'message' => 'Schedule updated successfully.',
            'data' => $schedule,
        ]);
    }

    public function cancel(Schedule $schedule)
    {
        $schedule->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'The schedule has been cancelled',
            'data' => $schedule,
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Your schedule has been deleted'
        ]);
    }
}