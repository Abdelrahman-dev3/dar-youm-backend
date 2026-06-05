<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Unit;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewReservations'), 403);

        $query = Reservation::with(['unit.property']);

        if (!$request->user()->isAdmin()) {
            if ($request->user()->isOwner()) {
                $query->whereHas('unit', function ($q) use ($request) {
                    $q->where('owner_id', $request->user()->id)
                        ->orWhereHas('property', fn ($property) => $property->where('user_id', $request->user()->id));
                });
            } else {
                $query->whereHas('unit.property', fn ($q) => $q->where('user_id', $request->user()->id));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest('check_in_date')->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageReservations'), 403);

        $validated = $request->validate($this->rules());
        $this->authorizeUnit($request, $validated['unit_id']);
        $validated['nights'] = $this->nights($validated);
        $validated['total_amount'] = $validated['total_amount'] ?? $validated['nights'] * $validated['price_per_night'];

        $reservation = Reservation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Reservation created successfully',
            'data' => $reservation->load(['unit.property']),
        ], 201);
    }

    public function show(Request $request, Reservation $reservation)
    {
        abort_unless($request->user()->hasPermission('canViewReservations'), 403);

        $this->authorizeReservation($request, $reservation);

        return response()->json([
            'success' => true,
            'data' => $reservation->load(['unit.property', 'messages', 'housekeepingTasks']),
        ]);
    }

    public function update(Request $request, Reservation $reservation)
    {
        abort_unless($request->user()->hasPermission('canManageReservations'), 403);

        $this->authorizeReservation($request, $reservation);
        $validated = $request->validate($this->rules(true));

        if (isset($validated['unit_id'])) {
            $this->authorizeUnit($request, $validated['unit_id']);
        }

        if (isset($validated['check_in_date'], $validated['check_out_date'])) {
            $validated['nights'] = $this->nights($validated);
        }

        $reservation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Reservation updated successfully',
            'data' => $reservation->fresh(['unit.property']),
        ]);
    }

    public function destroy(Request $request, Reservation $reservation)
    {
        abort_unless($request->user()->hasPermission('canManageReservations'), 403);

        $this->authorizeReservation($request, $reservation);
        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservation deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'unit_id' => [$required, 'uuid', 'exists:units,id'],
            'guest_name' => [$required, 'string', 'max:255'],
            'guest_email' => [$required, 'email', 'max:255'],
            'guest_phone' => [$required, 'string', 'max:255'],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'check_in_date' => [$required, 'date'],
            'check_out_date' => [$required, 'date', 'after:check_in_date'],
            'price_per_night' => [$required, 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'booking_source' => ['nullable', 'in:airbnb,booking_com,agoda,vrbo,direct,other'],
            'booking_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,confirmed,checked_in,checked_out,cancelled'],
            'payment_status' => ['nullable', 'in:unpaid,partial,paid,refunded'],
            'special_requests' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
        ];
    }

    private function nights(array $data): int
    {
        return max(1, now()->parse($data['check_in_date'])->diffInDays(now()->parse($data['check_out_date'])));
    }

    private function authorizeUnit(Request $request, string $unitId): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            Unit::where('id', $unitId)
                ->where(function ($q) use ($request) {
                    $q->where('owner_id', $request->user()->id)
                        ->orWhereHas('property', fn ($property) => $property->where('user_id', $request->user()->id));
                })
                ->exists(),
            403,
            'You are not authorized to manage this unit.'
        );
    }

    private function authorizeReservation(Request $request, Reservation $reservation): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            $reservation->unit()
                ->where(function ($q) use ($request) {
                    $q->where('owner_id', $request->user()->id)
                        ->orWhereHas('property', fn ($property) => $property->where('user_id', $request->user()->id));
                })
                ->exists(),
            403,
            'You are not authorized to manage this reservation.'
        );
    }
}
