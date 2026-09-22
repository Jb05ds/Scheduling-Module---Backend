<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use App\Http\Requests\StoreScheduleRequest;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::all();

        return response()->json([
            'data' => $schedules
        ]);
    }

    public function store(StoreScheduleRequest $request)
    {
        $validated = $request->validated();

        $schedule = Schedule::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'created_by' => 1,
            'status' => 'scheduled',
        ]);

        return response()->json([
            'message' => 'Schedule created successfully.',
            'data' => $schedule,
        ], 201);
    }

    public function show(Schedule $schedule)
    {
        return response()->json([
            'data' => $schedule
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'status' => ['sometimes', 'in:scheduled,completed,cancelled'],
        ]);

        $schedule->update($validated);

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