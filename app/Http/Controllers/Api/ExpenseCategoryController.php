<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasPermission('canViewFinance'), 403);

        $query = ExpenseCategory::withCount('expenses');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->paginate($request->get('per_page', 50)),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('canManageFinance'), 403);

        $validated = $request->validate($this->rules());
        $validated['created_by'] = $request->user()->id;

        $category = ExpenseCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully',
            'data' => $category->loadCount('expenses'),
        ], 201);
    }

    public function show(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_unless($request->user()->hasPermission('canViewFinance'), 403);

        return response()->json([
            'success' => true,
            'data' => $expenseCategory->loadCount('expenses'),
        ]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_unless($request->user()->hasPermission('canManageFinance'), 403);

        $validated = $request->validate($this->rules(true));
        $expenseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated successfully',
            'data' => $expenseCategory->fresh()->loadCount('expenses'),
        ]);
    }

    public function destroy(Request $request, ExpenseCategory $expenseCategory)
    {
        abort_unless($request->user()->hasPermission('canManageFinance'), 403);
        abort_if($expenseCategory->expenses()->exists(), 422, 'This expense category already has expenses and cannot be deleted.');

        $expenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category deleted successfully',
        ]);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'in:slate,teal,blue,green,orange,red,purple'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
