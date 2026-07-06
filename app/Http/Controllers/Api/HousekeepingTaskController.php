<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Unit;
use Illuminate\Http\Request;

class HousekeepingTaskController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->hasPermission('canViewHousekeeping') || $request->user()->hasPermission('canViewOwnTasks'),
            403
        );

        $query = HousekeepingTask::with(['unit.property', 'reservation', 'assignee']);

        if (!$request->user()->isAdmin()) {
            if ($request->user()->hasPermission('canManageHousekeeping') || $request->user()->hasPermission('canViewHousekeeping')) {
                $query->whereHas('unit.property', fn ($q) => $q->where('user_id', $request->user()->id));
            } elseif ($request->user()->hasPermission('canViewOwnTasks')) {
                $query->where('assigned_to', $request->user()->id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('scheduled_date')->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageHousekeeping'), 403);

        $validated = $request->validate($this->rules());
        $this->authorizeUnit($request, $validated['unit_id']);
        $this->authorizeReservationLink($validated);

        $task = HousekeepingTask::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Housekeeping task created successfully',
            'data' => $task->load(['unit.property.owner', 'reservation.unit.property.owner', 'assignee']),
        ], 201);
    }

    public function show(Request $request, HousekeepingTask $housekeepingTask)
    {
        abort_unless(
            $request->user()->hasPermission('canViewHousekeeping') || $request->user()->hasPermission('canViewOwnTasks'),
            403
        );

        $this->authorizeTask($request, $housekeepingTask);

        return response()->json([
            'success' => true,
            'data' => $housekeepingTask->load(['unit.property.owner', 'reservation.unit.property.owner', 'assignee']),
        ]);
    }

    public function update(Request $request, HousekeepingTask $housekeepingTask)
    {
        abort_unless($request->user()->hasPermission('canManageHousekeeping'), 403);

        $this->authorizeTask($request, $housekeepingTask);
        $validated = $request->validate($this->rules(true));

        if (isset($validated['unit_id'])) {
            $this->authorizeUnit($request, $validated['unit_id']);
        }

        $this->authorizeReservationLink(array_merge($housekeepingTask->only(['unit_id', 'reservation_id']), $validated));

        if (($validated['status'] ?? null) === 'in_progress' && !$housekeepingTask->started_at) {
            $validated['started_at'] = now();
        }

        if (in_array($validated['status'] ?? null, ['completed', 'verified'], true) && !$housekeepingTask->completed_at) {
            $validated['completed_at'] = now();
        }

        $housekeepingTask->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Housekeeping task updated successfully',
            'data' => $housekeepingTask->fresh(['unit.property.owner', 'reservation.unit.property.owner', 'assignee']),
        ]);
    }

    public function destroy(Request $request, HousekeepingTask $housekeepingTask)
    {
        abort_unless($request->user()->hasPermission('canManageHousekeeping'), 403);

        $this->authorizeTask($request, $housekeepingTask);
        $housekeepingTask->delete();

        return response()->json([
            'success' => true,
            'message' => 'Housekeeping task deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'unit_id' => [$required, 'uuid', 'exists:units,id'],
            'reservation_id' => ['nullable', 'uuid', 'exists:reservations,id'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'task_type' => ['nullable', 'in:checkout_cleaning,checkin_preparation,turnover,deep_cleaning,inspection'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'status' => ['nullable', 'in:pending,assigned,in_progress,completed,verified'],
            'scheduled_date' => [$required, 'date'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'checklist' => ['nullable', 'array'],
            'before_photos' => ['nullable', 'array'],
            'after_photos' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'issues_found' => ['nullable', 'string'],
        ];
    }

    private function authorizeUnit(Request $request, string $unitId): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            Unit::where('id', $unitId)->whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))->exists(),
            403,
            'You are not authorized to manage tasks for this unit.'
        );
    }

    private function authorizeTask(Request $request, HousekeepingTask $task): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        if ($request->user()->hasPermission('canManageHousekeeping') || $request->user()->hasPermission('canViewHousekeeping')) {
            abort_unless(
                $task->unit()->whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))->exists(),
                403,
                'You are not authorized to manage this housekeeping task.'
            );
            return;
        }

        if ($request->user()->hasPermission('canViewOwnTasks')) {
            abort_unless($task->assigned_to === $request->user()->id, 403, 'You are not authorized to manage this housekeeping task.');
            return;
        }

        abort_unless(
            $task->unit()->whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))->exists(),
            403,
            'You are not authorized to manage this housekeeping task.'
        );
    }

    private function authorizeReservationLink(array $data): void
    {
        if (empty($data['reservation_id']) || empty($data['unit_id'])) {
            return;
        }

        abort_unless(
            Reservation::where('id', $data['reservation_id'])
                ->where('unit_id', $data['unit_id'])
                ->exists(),
            422,
            'Selected reservation must belong to the same unit.'
        );
    }
}
