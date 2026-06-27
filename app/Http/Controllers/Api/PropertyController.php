<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    /**
     * Display a listing of properties
     */
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewProperties'), 403);

        $query = Property::with(['user', 'owner', 'units.owner']);

        if (!$request->user()->isAdmin()) {
            if ($request->user()->isOwner()) {
                $query->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id)
                        ->orWhere('owner_id', $request->user()->id)
                        ->orWhereHas('units', fn ($unit) => $unit->where('owner_id', $request->user()->id));
                });
            } else {
                $query->where('user_id', $request->user()->id);
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $properties = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $properties,
        ]);
    }

    /**
     * Store a newly created property
     */
    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageProperties'), 403);

        $validated = $request->validate([
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('role', 'owner')],
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'address' => 'required|string',
            'address_ar' => 'nullable|string',
            'city' => 'required|string',
            'city_ar' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'property_type' => 'required|string',
            'total_units' => 'nullable|integer|min:0',
            'cover_image_url' => 'nullable|url',
            'amenities' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,maintenance',
            'is_listed' => 'nullable|boolean',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['owner_id'] = $validated['owner_id'] ?? ($request->user()->isOwner() ? $request->user()->id : null);

        $property = Property::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة العقار بنجاح',
            'data' => $property->load(['user', 'owner', 'units.owner']),
        ], 201);
    }

    /**
     * Display the specified property
     */
    public function show(Request $request, string $id)
    {
        abort_unless($request->user()->hasPermission('canViewProperties'), 403);

        $property = Property::with([
            'user',
            'owner',
            'units.owner',
            'units.reservations.housekeepingTasks',
            'units.maintenanceTickets',
        ])
            ->findOrFail($id);

        // Authorization check
        if (!$this->canAccessProperty($request, $property)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول لهذا العقار',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $property,
        ]);
    }

    /**
     * Update the specified property
     */
    public function update(Request $request, string $id)
    {
        abort_unless($request->user()->hasPermission('canManageProperties'), 403);

        $property = Property::findOrFail($id);

        // Authorization check
        if (!$this->canAccessProperty($request, $property)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بتعديل هذا العقار',
            ], 403);
        }

        $validated = $request->validate([
            'owner_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('role', 'owner')],
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'address' => 'sometimes|string',
            'address_ar' => 'nullable|string',
            'city' => 'sometimes|string',
            'city_ar' => 'nullable|string',
            'state' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'property_type' => 'sometimes|string',
            'total_units' => 'nullable|integer|min:0',
            'cover_image_url' => 'nullable|url',
            'amenities' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive,maintenance',
            'is_listed' => 'nullable|boolean',
        ]);

        $property->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث العقار بنجاح',
            'data' => $property->fresh(['user', 'owner', 'units.owner']),
        ]);
    }

    /**
     * Remove the specified property
     */
    public function destroy(Request $request, string $id)
    {
        abort_unless($request->user()->hasPermission('canManageProperties'), 403);

        $property = Property::findOrFail($id);

        // Authorization check
        if (!$this->canAccessProperty($request, $property)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بحذف هذا العقار',
            ], 403);
        }

        $property->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف العقار بنجاح',
        ]);
    }

    /**
     * Get property statistics
     */
    public function statistics(Request $request, string $id)
    {
        abort_unless($request->user()->hasPermission('canViewReports') || $request->user()->hasPermission('canViewProperties'), 403);

        $property = Property::with(['units.reservations'])
            ->findOrFail($id);

        // Authorization check
        if (!$this->canAccessProperty($request, $property)) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول لهذا العقار',
            ], 403);
        }

        $occupiedUnits = $property->units->filter(function ($unit) {
            if ($unit->status === 'occupied') {
                return true;
            }

            return $unit->reservations->contains(function ($reservation) {
                return in_array($reservation->status, ['confirmed', 'checked_in'], true)
                    && optional($reservation->check_in_date)->startOfDay()->lte(now()->startOfDay())
                    && optional($reservation->check_out_date)->startOfDay()->gte(now()->startOfDay());
            });
        })->count();

        $stats = [
            'total_units' => $property->units->count(),
            'available_units' => $property->units->where('status', 'available')->count(),
            'occupied_units' => $occupiedUnits,
            'cleaning_units' => $property->units->where('status', 'cleaning')->count(),
            'maintenance_units' => $property->units->where('status', 'maintenance')->count(),
            'occupancy_rate' => $property->occupancy_rate,
            'total_reservations' => $property->units->sum(function ($unit) {
                return $unit->reservations->count();
            }),
            'active_reservations' => $property->units->sum(function ($unit) {
                return $unit->reservations->where('status', 'checked_in')->count();
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    private function canAccessProperty(Request $request, Property $property): bool
    {
        if ($request->user()->isAdmin()) {
            return true;
        }

        if ($property->user_id === $request->user()->id) {
            return true;
        }

        return $request->user()->isOwner()
            && (
                $property->owner_id === $request->user()->id
                || $property->units()->where('owner_id', $request->user()->id)->exists()
            );
    }
}
