<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Models\Unit;
use Illuminate\Http\Request;

class MaintenanceTicketController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->hasPermission('canViewMaintenance') || $request->user()->hasPermission('canViewOwnTickets'),
            403
        );

        $query = MaintenanceTicket::with(['unit.property.owner', 'reporter', 'assignee']);

        if ($request->user()->hasPermission('canViewOwnTickets')) {
            $query->where(function ($q) use ($request) {
                $q->where('assigned_to', $request->user()->id)
                    ->orWhere('reported_by', $request->user()->id);
            });
        } elseif (!$request->user()->isAdmin()) {
            $query->whereHas('unit.property', fn ($q) => $q->where('user_id', $request->user()->id));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageMaintenance'), 403);

        $validated = $request->validate($this->rules());
        $this->authorizeUnit($request, $validated['unit_id']);
        $validated['reported_by'] = $validated['reported_by'] ?? $request->user()->id;

        $ticket = MaintenanceTicket::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance ticket created successfully',
            'data' => $ticket->load(['unit.property.owner', 'reporter', 'assignee']),
        ], 201);
    }

    public function show(Request $request, MaintenanceTicket $maintenanceTicket)
    {
        abort_unless(
            $request->user()->hasPermission('canViewMaintenance') || $request->user()->hasPermission('canViewOwnTickets'),
            403
        );

        $this->authorizeTicket($request, $maintenanceTicket);

        return response()->json([
            'success' => true,
            'data' => $maintenanceTicket->load(['unit.property.owner', 'reporter', 'assignee']),
        ]);
    }

    public function update(Request $request, MaintenanceTicket $maintenanceTicket)
    {
        abort_unless($request->user()->hasPermission('canManageMaintenance'), 403);

        $this->authorizeTicket($request, $maintenanceTicket);
        $validated = $request->validate($this->rules(true));

        if (isset($validated['unit_id'])) {
            $this->authorizeUnit($request, $validated['unit_id']);
        }

        if (($validated['status'] ?? null) === 'in_progress' && !$maintenanceTicket->started_at) {
            $validated['started_at'] = now();
        }

        if (in_array($validated['status'] ?? null, ['completed', 'closed'], true) && !$maintenanceTicket->completed_at) {
            $validated['completed_at'] = now();
        }

        $maintenanceTicket->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance ticket updated successfully',
            'data' => $maintenanceTicket->fresh(['unit.property.owner', 'reporter', 'assignee']),
        ]);
    }

    public function destroy(Request $request, MaintenanceTicket $maintenanceTicket)
    {
        abort_unless($request->user()->hasPermission('canManageMaintenance'), 403);

        $this->authorizeTicket($request, $maintenanceTicket);
        $maintenanceTicket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Maintenance ticket deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'unit_id' => [$required, 'uuid', 'exists:units,id'],
            'reported_by' => ['nullable', 'uuid', 'exists:users,id'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'title' => [$required, 'string', 'max:255'],
            'description' => [$required, 'string'],
            'category' => ['nullable', 'in:plumbing,electrical,hvac,appliance,structural,cosmetic,other'],
            'priority' => ['nullable', 'in:low,medium,high,critical'],
            'status' => ['nullable', 'in:open,assigned,in_progress,pending_parts,completed,closed'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'due_date' => ['nullable', 'date'],
            'photos' => ['nullable', 'array'],
            'resolution_notes' => ['nullable', 'string'],
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
            'You are not authorized to manage tickets for this unit.'
        );
    }

    private function authorizeTicket(Request $request, MaintenanceTicket $ticket): void
    {
        if ($request->user()->hasPermission('canViewOwnTickets')) {
            abort_unless(
                $ticket->assigned_to === $request->user()->id || $ticket->reported_by === $request->user()->id,
                403,
                'You are not authorized to manage this maintenance ticket.'
            );
            return;
        }

        if ($request->user()->isAdmin() || $ticket->reported_by === $request->user()->id) {
            return;
        }

        abort_unless(
            $ticket->unit()->whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))->exists(),
            403,
            'You are not authorized to manage this maintenance ticket.'
        );
    }
}
