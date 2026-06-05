<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewUnits'), 403);

        $query = Unit::with(['property', 'owner']);

        if (!$request->user()->isAdmin()) {
            if ($request->user()->isOwner()) {
                $query->where(function ($q) use ($request) {
                    $q->where('owner_id', $request->user()->id)
                        ->orWhereHas('property', fn ($property) => $property->where('user_id', $request->user()->id));
                });
            } else {
                $query->whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id));
            }
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 15)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageUnits'), 403);

        $validated = $request->validate($this->rules($request));
        $this->authorizeProperty($request, $validated['property_id']);

        $unit = Unit::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Unit created successfully',
            'data' => $unit->load(['property', 'owner']),
        ], 201);
    }

    public function show(Request $request, Unit $unit)
    {
        abort_unless($request->user()->hasPermission('canViewUnits'), 403);

        $this->authorizeUnit($request, $unit);

        return response()->json([
            'success' => true,
            'data' => $unit->load(['property', 'owner', 'reservations']),
        ]);
    }

    public function update(Request $request, Unit $unit)
    {
        abort_unless($request->user()->hasPermission('canManageUnits'), 403);

        $this->authorizeUnit($request, $unit);

        $validated = $request->validate($this->rules($request, true));

        if (isset($validated['property_id'])) {
            $this->authorizeProperty($request, $validated['property_id']);
        }

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Unit updated successfully',
            'data' => $unit->fresh(['property', 'owner']),
        ]);
    }

    public function destroy(Request $request, Unit $unit)
    {
        abort_unless($request->user()->hasPermission('canManageUnits'), 403);

        $this->authorizeUnit($request, $unit);
        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unit deleted successfully',
        ]);
    }

    private function rules(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'property_id' => [$required, 'uuid', 'exists:properties,id'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'unit_number' => [$required, 'string', 'max:255'],
            'unit_name' => ['nullable', 'string', 'max:255'],
            'unit_name_ar' => ['nullable', 'string', 'max:255'],
            'unit_type' => [$required, 'string', 'max:255'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'in:available,occupied,cleaning,maintenance,blocked'],
            'floor_number' => ['nullable', 'string', 'max:255'],
            'amenities' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'cleaning_notes' => ['nullable', 'string'],
            'maintenance_notes' => ['nullable', 'string'],
        ];
    }

    private function authorizeProperty(Request $request, string $propertyId): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            Property::where('id', $propertyId)->where('user_id', $request->user()->id)->exists(),
            403,
            'You are not authorized to manage this property.'
        );
    }

    private function authorizeUnit(Request $request, Unit $unit): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        if ($request->user()->isOwner() && $unit->owner_id === $request->user()->id) {
            return;
        }

        abort_unless($unit->property()->where('user_id', $request->user()->id)->exists(), 403, 'You are not authorized to manage this unit.');
    }
}
