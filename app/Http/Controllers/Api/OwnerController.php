<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OwnerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewOwners'), 403);

        $query = $this->ownerQuery($request)->withCount(['ownedProperties', 'ownedUnits']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('owner_reference_number', $search);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 50)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageOwners'), 403);

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'language_preference' => ['nullable', 'in:ar,en'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $owner = DB::transaction(function () use ($validated) {
            return User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'] ?? null,
                'role' => 'owner',
                'owner_reference_number' => $this->nextOwnerReferenceNumber(),
                'company_name' => $validated['company_name'] ?? null,
                'language_preference' => $validated['language_preference'] ?? 'ar',
                'is_active' => $validated['is_active'] ?? true,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Owner created successfully',
            'data' => $owner->fresh(),
        ], 201);
    }

    public function show(Request $request, User $owner)
    {
        abort_unless($request->user()->hasPermission('canViewOwners'), 403);
        abort_unless($owner->role === 'owner', 404);
        $this->authorizeOwnerAccess($request, $owner);

        $owner->load([
            'ownedProperties.owner',
            'ownedProperties.units.owner',
            'ownedUnits.property.owner',
            'ownedUnits.reservations.unit.property',
        ])->loadCount(['ownedProperties', 'ownedUnits']);

        $reservations = $owner->ownedUnits
            ->flatMap(fn ($unit) => $unit->reservations)
            ->sortByDesc('check_in_date')
            ->values();

        return response()->json([
            'success' => true,
            'data' => array_merge($owner->toArray(), [
                'properties' => $owner->ownedProperties->values(),
                'units' => $owner->ownedUnits->values(),
                'reservations' => $reservations,
            ]),
        ]);
    }

    public function update(Request $request, User $owner)
    {
        abort_unless($request->user()->hasPermission('canManageOwners'), 403);
        abort_unless($owner->role === 'owner', 404);
        $this->authorizeOwnerAccess($request, $owner);

        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($owner->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'language_preference' => ['nullable', 'in:ar,en'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $owner->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Owner updated successfully',
            'data' => $owner->fresh(),
        ]);
    }

    public function destroy(Request $request, User $owner)
    {
        abort_unless($request->user()->hasPermission('canManageOwners'), 403);
        abort_unless($owner->role === 'owner', 404);
        $this->authorizeOwnerAccess($request, $owner);

        DB::transaction(function () use ($owner) {
            $owner->ownedProperties()->update(['owner_id' => null]);
            $owner->ownedUnits()->update(['owner_id' => null]);
            $owner->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Owner deleted successfully',
        ]);
    }

    private function ownerQuery(Request $request): Builder
    {
        $query = User::query()->where('role', 'owner');

        if ($request->user()->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($request) {
            $builder->whereHas('ownedProperties', fn (Builder $property) => $property->where('user_id', $request->user()->id))
                ->orWhereHas('ownedUnits.property', fn (Builder $property) => $property->where('user_id', $request->user()->id));
        });
    }

    private function authorizeOwnerAccess(Request $request, User $owner): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            $this->ownerQuery($request)->whereKey($owner->id)->exists(),
            403,
            'You are not authorized to access this owner.'
        );
    }

    private function nextOwnerReferenceNumber(): int
    {
        return (int) User::query()
            ->where('role', 'owner')
            ->lockForUpdate()
            ->max('owner_reference_number') + 1;
    }
}
