<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewFinance'), 403);

        $query = Expense::with(['property.owner', 'category', 'creator']);
        $this->scopeExpenses($request, $query);

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('expense_date')->orderByDesc('created_at')->paginate($request->get('per_page', 50)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageFinance'), 403);

        $validated = $request->validate($this->rules());
        $this->authorizeProperty($request, $validated['property_id']);
        $validated['created_by'] = $request->user()->id;

        $expense = Expense::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully',
            'data' => $expense->load(['property.owner', 'category', 'creator']),
        ], 201);
    }

    public function show(Request $request, Expense $expense)
    {
        abort_unless($request->user()->hasPermission('canViewFinance'), 403);
        $this->authorizeExpense($request, $expense);

        return response()->json([
            'success' => true,
            'data' => $expense->load(['property.owner', 'category', 'creator']),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        abort_unless($request->user()->hasPermission('canManageFinance'), 403);
        $this->authorizeExpense($request, $expense);

        $validated = $request->validate($this->rules(true));

        if (isset($validated['property_id'])) {
            $this->authorizeProperty($request, $validated['property_id']);
        }

        $expense->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully',
            'data' => $expense->fresh(['property.owner', 'category', 'creator']),
        ]);
    }

    public function destroy(Request $request, Expense $expense)
    {
        abort_unless($request->user()->hasPermission('canManageFinance'), 403);
        $this->authorizeExpense($request, $expense);
        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'property_id' => [$required, 'uuid', 'exists:properties,id'],
            'expense_category_id' => [$required, 'uuid', 'exists:expense_categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => [$required, 'numeric', 'min:0'],
            'expense_date' => [$required, 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function scopeExpenses(Request $request, Builder $query): void
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isOwner()) {
            $query->whereHas('property', function (Builder $property) use ($user) {
                $property->where('user_id', $user->id)
                    ->orWhere('owner_id', $user->id)
                    ->orWhereHas('units', fn (Builder $unit) => $unit->where('owner_id', $user->id));
            });
            return;
        }

        $query->whereHas('property', fn (Builder $property) => $property->where('user_id', $user->id));
    }

    private function authorizeProperty(Request $request, string $propertyId): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        $property = Property::query()->where('id', $propertyId);

        if ($request->user()->isOwner()) {
            $property->where(function (Builder $query) use ($request) {
                $query->where('user_id', $request->user()->id)
                    ->orWhere('owner_id', $request->user()->id)
                    ->orWhereHas('units', fn (Builder $unit) => $unit->where('owner_id', $request->user()->id));
            });
        } else {
            $property->where('user_id', $request->user()->id);
        }

        abort_unless($property->exists(), 403, 'You are not authorized to manage expenses for this property.');
    }

    private function authorizeExpense(Request $request, Expense $expense): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        $query = $expense->property();

        if ($request->user()->isOwner()) {
            $query->where(function (Builder $property) use ($request) {
                $property->where('user_id', $request->user()->id)
                    ->orWhere('owner_id', $request->user()->id)
                    ->orWhereHas('units', fn (Builder $unit) => $unit->where('owner_id', $request->user()->id));
            });
        } else {
            $query->where('user_id', $request->user()->id);
        }

        abort_unless($query->exists(), 403, 'You are not authorized to manage this expense.');
    }
}
