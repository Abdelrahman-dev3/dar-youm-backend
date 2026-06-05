<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewMessages'), 403);

        $query = Message::with(['reservation.unit.property', 'user']);

        if (!$request->user()->isAdmin()) {
            $query->where(function ($q) use ($request) {
                $q->where('user_id', $request->user()->id)
                    ->orWhereHas('reservation.unit.property', fn ($p) => $p->where('user_id', $request->user()->id));
            });
        }

        if ($request->filled('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        if ($request->filled('requires_action')) {
            $query->where('requires_action', $request->boolean('requires_action'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageMessages'), 403);

        $validated = $request->validate($this->rules());
        $this->authorizeReservation($request, $validated['reservation_id'] ?? null);
        $validated['user_id'] = $validated['user_id'] ?? $request->user()->id;

        $message = Message::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Message created successfully',
            'data' => $message->load(['reservation.unit.property', 'user']),
        ], 201);
    }

    public function show(Request $request, Message $message)
    {
        abort_unless($request->user()->hasPermission('canViewMessages'), 403);

        $this->authorizeMessage($request, $message);

        return response()->json([
            'success' => true,
            'data' => $message->load(['reservation.unit.property', 'user']),
        ]);
    }

    public function update(Request $request, Message $message)
    {
        abort_unless($request->user()->hasPermission('canManageMessages'), 403);

        $this->authorizeMessage($request, $message);
        $validated = $request->validate($this->rules(true));

        if (isset($validated['reservation_id'])) {
            $this->authorizeReservation($request, $validated['reservation_id']);
        }

        if (($validated['is_read'] ?? false) && !$message->read_at) {
            $validated['read_at'] = now();
        }

        $message->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Message updated successfully',
            'data' => $message->fresh(['reservation.unit.property', 'user']),
        ]);
    }

    public function destroy(Request $request, Message $message)
    {
        abort_unless($request->user()->hasPermission('canManageMessages'), 403);

        $this->authorizeMessage($request, $message);
        $message->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'reservation_id' => ['nullable', 'uuid', 'exists:reservations,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'guest_name' => [$required, 'string', 'max:255'],
            'guest_email' => [$required, 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:255'],
            'channel' => [$required, 'string', 'max:255'],
            'direction' => [$required, 'in:incoming,outgoing'],
            'message_content' => [$required, 'string'],
            'is_read' => ['nullable', 'boolean'],
            'requires_action' => ['nullable', 'boolean'],
            'ai_suggested_reply' => ['nullable', 'string'],
            'read_at' => ['nullable', 'date'],
            'replied_at' => ['nullable', 'date'],
        ];
    }

    private function authorizeReservation(Request $request, ?string $reservationId): void
    {
        if (!$reservationId || $request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            Reservation::where('id', $reservationId)
                ->whereHas('unit.property', fn ($q) => $q->where('user_id', $request->user()->id))
                ->exists(),
            403,
            'You are not authorized to manage messages for this reservation.'
        );
    }

    private function authorizeMessage(Request $request, Message $message): void
    {
        if ($request->user()->isAdmin() || $message->user_id === $request->user()->id) {
            return;
        }

        abort_unless(
            $message->reservation()
                ->whereHas('unit.property', fn ($q) => $q->where('user_id', $request->user()->id))
                ->exists(),
            403,
            'You are not authorized to manage this message.'
        );
    }
}
