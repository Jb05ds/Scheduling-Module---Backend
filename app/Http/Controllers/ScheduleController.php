<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use Illuminate\Validation\ValidationException;
use App\Notifications\ScheduleAssigned;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{

    public function index(Request $request)
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:mine,assigned'],
            'status' => ['nullable', 'in:scheduled,completed,cancelled'],
            'scheduled_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->getAuthIdentifier();

        $query = Schedule::with(['creator', 'assignee']);

        if (($validated['scope'] ?? 'mine') === 'assigned') {
            $query->where('created_by', $userId)
                ->whereNotNull('assigned_to')
                ->where('assigned_to', '!=', $userId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to', $userId)
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('created_by', $userId)
                            ->whereNull('assigned_to');
                    });
            });
        }

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

        if($request->input('assigned_to') !== null
            && $request->input('assigned_to') !== '') {

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
        }


        if (empty($validated['repeat_type'])) {
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

            $this->notifyAssignee($schedule, $request);

            return response()->json([
                'message' => 'Schedule created successfully.',
                'data' => $schedule,
            ], 201);
        }

        //my recur option logic
        $series_id = (string) Str::uuid();

        $repeat_type = $validated['repeat_type'];

        $startDate = Carbon::parse($validated['scheduled_date'])->toImmutable();

        $currentDate = Carbon::parse($validated['scheduled_date'])->toImmutable();

        $repeat_until = Carbon::parse($validated['repeat_until']);

        $dateRecurs = collect();

        $monthsElapsed = 0;

        while ($currentDate <= $repeat_until) {
            $dateRecurs->push($currentDate->format('Y-m-d'));

            $monthsElapsed++;

            $nextDate = match($repeat_type) {
            'daily' =>  $currentDate->addDay(),
            'weekly' => $currentDate->addWeek(),
            'monthly' => $startDate->addMonthsNoOverflow($monthsElapsed)
            };

            if (count($dateRecurs) > 80) {
                throw ValidationException::withMessages([
                    'scheduled_date' => 'Date recurred max the limit',
                ]);
            }
            $currentDate = $nextDate;
        }

            DB::transaction(function () use ($dateRecurs, $validated, $series_id, $repeat_type, $repeat_until, $request) {
                if ($request->input('assigned_to') !== null && $request->input('assigned_to') !== '') {
                
                $conflict = Schedule::whereIn('scheduled_date', $dateRecurs->toArray())
                    ->where('assigned_to', '=', $validated['assigned_to'])
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($query) use ($validated) {
                        $query->where('start_time', '<', $validated['end_time'])
                            ->where('end_time', '>', $validated['start_time']);
                    })
                    ->lockForUpdate()
                    ->exists();

                    if ($conflict) {
                        throw ValidationException::withMessages([
                            'generated_date' => 'The schedule overlaps existing schedules',
                        ]);
                    }
                }

                $now = Carbon::now();
                $rowsToInsert = [];
                
                foreach ($dateRecurs as $dateRecur) {
                    $rowsToInsert[] = [
                        'series_id'      => $series_id,
                        'title'          => $validated['title'],
                        'description'    => $validated['description'] ?? null,
                        'created_by'     => $request->user()->getAuthIdentifier(),
                        'assigned_to'    => $validated['assigned_to'] ?? null,
                        'scheduled_date' => $dateRecur,
                        'start_time'     => $validated['start_time'],
                        'end_time'       => $validated['end_time'],
                        'status'         => 'scheduled',
                        'repeat_type'    => $repeat_type,
                        'repeat_until'   => $repeat_until->format('Y-m-d'),
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }

                Schedule::insert($rowsToInsert);
            });
            return response()->json([
                    'message' => 'Schedules has been created',
                    'data' => $series_id
                ], 201);
    }

    public function show(Request $request, Schedule $schedule)
    {
        $this->authorizeParticipant($request, $schedule);

        $schedule->load(['creator', 'assignee']);

        return response()->json([
            'data' => $schedule
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule)
    {
        $this->authorizeCreator($request, $schedule);

        $validated = $request->validated();
        $previousAssignee = $schedule->assigned_to;

        $resetReminder =
            substr((string) $schedule->scheduled_date, 0, 10) !== $validated['scheduled_date']
            || substr((string) $schedule->start_time, 0, 5) !== substr($validated['start_time'], 0, 5)
            || (int) $schedule->assigned_to !== (int) ($validated['assigned_to'] ?? 0);

        if (!empty($validated['assigned_to'])) {
            $conflict = Schedule::where('scheduled_date', $validated['scheduled_date'])
                ->where('assigned_to', $validated['assigned_to'])
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
        }

        $schedule->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'assigned_to' => $validated['assigned_to'] ?? null,
            'status' => $validated['status'] ?? $schedule->status,
            ...($resetReminder ? ['reminder_sent_at' => null] : []),
        ]);

        if ((int) $schedule->assigned_to !== (int) $previousAssignee) {
            $this->notifyAssignee($schedule, $request);
        }

        return response()->json([
            'message' => 'Schedule updated successfully.',
            'data' => $schedule->load(['creator', 'assignee']),
        ]);
    }

    public function cancel(Request $request, Schedule $schedule)
    {
        $this->authorizeCreator($request, $schedule);

        if($schedule->status == 'scheduled') {
            $schedule->update([
                'status' => 'cancelled'
            ]);

            return response()->json([
                'message' => 'The schedule has been cancelled',
                'data' => $schedule,
            ]);
        } else {
            return response()->json([
                'message' => 'Only scheduled items can be cancelled'
            ]);
        }
    }
    
    public function destroy(Request $request, Schedule $schedule)
    {
        $this->authorizeCreator($request, $schedule);

        $schedule->delete();

        return response()->json([
            'message' => 'Your schedule has been deleted'
        ]);
    }

    public function complete(Request $request, Schedule $schedule)
    {
        $this->authorizeParticipant($request, $schedule);

        if ($schedule->status == 'scheduled') {

            $schedule->update([
                'status' => 'completed'
            ]);

            return response()->json([
                'message' => 'The schedule has been completed',
                'data' => $schedule,
            ]);
        } else {
            return response()->json([
                'message' => 'Only scheduled items can be marked as completed',
            ]);
        }
    }

    public function repeat(Request $request, Schedule $schedule) 
    {
        $validated = $request->validated();
    }

    private function notifyAssignee(Schedule $schedule, Request $request): void
    {
        $assigneeId = $schedule->assigned_to;


        if (!$assigneeId || (int) $assigneeId === (int) $request->user()->getAuthIdentifier()) {
            return;
        }

        try {
            $schedule->assignee?->notify(new ScheduleAssigned($schedule));
        } catch (\Throwable $e) {
            Log::warning('Could not send schedule push notification: ' . $e->getMessage());
        }
    }

    private function authorizeParticipant(Request $request, Schedule $schedule): void
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        abort_unless(
            (int) $schedule->created_by === $userId || (int) $schedule->assigned_to === $userId,
            403,
            'You do not have access to this schedule.'
        );
    }

    private function authorizeCreator(Request $request, Schedule $schedule): void
    {
        abort_unless(
            (int) $schedule->created_by === (int) $request->user()->getAuthIdentifier(),
            403,
            'Only the person who created this schedule can do that.'
        );
    }
}
