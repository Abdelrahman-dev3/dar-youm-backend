<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission('canViewDashboard')
            || $user->hasPermission('canViewReports')
            || $user->hasPermission('canViewFinance')
            || $user->hasPermission('canViewOwnTasks')
            || $user->hasPermission('canViewOwnTickets'),
            403
        );

        $units = $this->unitsFor($request);
        $reservations = $this->reservationsFor($request);
        $reservationRows = $reservations->get();
        $expenseRows = $this->expensesFor($request)->with(['property.owner', 'unit', 'category'])->get();
        $unitCount = (clone $units)->count();
        $occupiedUnits = $this->occupiedUnitsCount($request);
        $totalRevenue = (float) $reservationRows->sum('total_amount');
        $serviceFees = (float) $reservationRows->sum('service_fee');
        $vatAmount = (float) $reservationRows->sum('vat_amount');
        $totalExpenses = (float) $expenseRows->sum('amount');
        $netProfit = $totalRevenue - $totalExpenses - $serviceFees - $vatAmount;
        $ownerReceivables = max(0, $totalRevenue - $serviceFees - $vatAmount);
        $financeTrends = $this->financeTrends($reservationRows, $expenseRows);
        $financeTransactions = $this->financeTransactions($reservationRows, $expenseRows);
        $averageDailyRate = $reservationRows->count() > 0 ? round((float) $reservationRows->avg('price_per_night'), 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'total_revenue' => $totalRevenue,
                    'occupancy_rate' => $unitCount > 0 ? round(($occupiedUnits / $unitCount) * 100, 1) : 0,
                    'average_daily_rate' => $averageDailyRate,
                    'revpar' => $unitCount > 0 ? round($totalRevenue / $unitCount, 2) : 0,
                    'net_profit' => round($netProfit, 2),
                    'properties_count' => $this->propertiesFor($request)->count(),
                    'units_count' => $unitCount,
                    'reservations_count' => $reservationRows->count(),
                    'messages_count' => $user->hasPermission('canViewMessages') ? $this->messagesCount($request) : 0,
                ],
                'unit_statuses' => $this->unitStatuses($request),
                'monthly_revenue' => $this->monthlyRevenue($request),
                'booking_sources' => $this->bookingSources($request),
                'recent_reservations' => $user->hasPermission('canViewReservations')
                    ? $this->reservationsFor($request)->latest('check_in_date')->limit(5)->get()
                    : [],
                'today_operations' => [
                    'housekeeping' => $this->housekeepingFor($request)->latest('scheduled_date')->limit(3)->get(),
                    'maintenance' => $this->maintenanceFor($request)->latest()->limit(3)->get(),
                ],
                'finance' => [
                    'total_income' => round($totalRevenue, 2),
                    'commissions_paid' => round($serviceFees, 2),
                    'owner_receivables' => round($ownerReceivables, 2),
                    'transactions_count' => count($financeTransactions),
                    'paid_revenue' => (float) $reservationRows->where('payment_status', 'paid')->sum('total_amount'),
                    'unpaid_revenue' => (float) $reservationRows->whereIn('payment_status', ['unpaid', 'partial'])->sum('total_amount'),
                    'service_fees' => $serviceFees,
                    'vat_amount' => $vatAmount,
                    'cleaning_fees' => (float) $reservationRows->sum('cleaning_fee'),
                    'total_expenses' => round($totalExpenses, 2),
                    'net_profit' => round($netProfit, 2),
                    'trends' => $financeTrends,
                    'transactions' => $financeTransactions,
                ],
                'owners' => $user->hasPermission('canViewOwners') ? $this->ownersFor($request) : [],
                'staff' => [
                    'housekeeping' => $user->hasPermission('canViewHousekeeping') || $user->hasPermission('canManageHousekeeping')
                        ? $this->staffFor($request, ['housekeeping_supervisor', 'cleaner'])
                        : [],
                    'maintenance' => $user->hasPermission('canViewMaintenance') || $user->hasPermission('canManageMaintenance')
                        ? $this->staffFor($request, ['maintenance_staff'])
                        : [],
                ],
            ],
        ]);
    }

    private function propertiesFor(Request $request): Builder
    {
        $query = Property::query();
        $user = $request->user();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isOwner()) {
            return $query->where('user_id', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('units', fn (Builder $q) => $q->where('owner_id', $user->id));
        }

        return $query->where('user_id', $user->id);
    }

    private function unitsFor(Request $request): Builder
    {
        $query = Unit::query();
        $user = $request->user();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isOwner()) {
            return $query->where('owner_id', $user->id)
                ->orWhereHas('property', fn (Builder $q) => $q
                    ->where('user_id', $user->id)
                    ->orWhere('owner_id', $user->id));
        }

        return $query->whereHas('property', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    private function reservationsFor(Request $request): Builder
    {
        $query = Reservation::with(['unit.property']);
        $user = $request->user();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isOwner()) {
            return $query->whereHas('unit', fn (Builder $q) => $q->where('owner_id', $user->id)
                ->orWhereHas('property', fn (Builder $property) => $property
                    ->where('user_id', $user->id)
                    ->orWhere('owner_id', $user->id)));
        }

        return $query->whereHas('unit.property', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    private function housekeepingFor(Request $request): Builder
    {
        $query = HousekeepingTask::with(['unit.property', 'assignee']);
        $user = $request->user();

        if ($user->hasPermission('canViewOwnTasks')) {
            return $query->where('assigned_to', $user->id);
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('unit.property', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    private function expensesFor(Request $request): Builder
    {
        $query = Expense::with(['property.owner', 'unit', 'category']);
        $user = $request->user();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isOwner()) {
            return $query->whereHas('property', fn (Builder $q) => $q
                ->where('user_id', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('units', fn (Builder $unit) => $unit->where('owner_id', $user->id)));
        }

        return $query->whereHas('property', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    private function maintenanceFor(Request $request): Builder
    {
        $query = MaintenanceTicket::with(['unit.property', 'assignee', 'reporter']);
        $user = $request->user();

        if ($user->hasPermission('canViewOwnTickets')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('assigned_to', $user->id)->orWhere('reported_by', $user->id);
            });
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('unit.property', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    private function financeTrends(Collection $reservations, Collection $expenses): array
    {
        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd = Carbon::now()->endOfMonth();
        $previousStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        $currentReservations = $reservations->filter(fn ($reservation) => $this->dateValue($reservation->check_in_date)?->between($currentStart, $currentEnd));
        $previousReservations = $reservations->filter(fn ($reservation) => $this->dateValue($reservation->check_in_date)?->between($previousStart, $previousEnd));
        $currentExpenses = $expenses->filter(fn ($expense) => $this->dateValue($expense->expense_date)?->between($currentStart, $currentEnd));
        $previousExpenses = $expenses->filter(fn ($expense) => $this->dateValue($expense->expense_date)?->between($previousStart, $previousEnd));

        $currentIncome = (float) $currentReservations->sum('total_amount');
        $previousIncome = (float) $previousReservations->sum('total_amount');
        $currentCommissions = (float) $currentReservations->sum('service_fee');
        $previousCommissions = (float) $previousReservations->sum('service_fee');
        $currentVat = (float) $currentReservations->sum('vat_amount');
        $previousVat = (float) $previousReservations->sum('vat_amount');
        $currentOwnerReceivables = max(0, $currentIncome - $currentCommissions - $currentVat);
        $previousOwnerReceivables = max(0, $previousIncome - $previousCommissions - $previousVat);
        $currentNetProfit = $currentIncome - (float) $currentExpenses->sum('amount') - $currentCommissions - $currentVat;
        $previousNetProfit = $previousIncome - (float) $previousExpenses->sum('amount') - $previousCommissions - $previousVat;

        return [
            'total_income' => $this->trendPayload($currentIncome, $previousIncome),
            'commissions_paid' => $this->trendPayload($currentCommissions, $previousCommissions),
            'owner_receivables' => $this->trendPayload($currentOwnerReceivables, $previousOwnerReceivables),
            'net_profit' => $this->trendPayload($currentNetProfit, $previousNetProfit),
        ];
    }

    private function financeTransactions(Collection $reservations, Collection $expenses): array
    {
        $transactions = [];

        foreach ($reservations as $reservation) {
            $unitName = $reservation->unit?->unit_name ?: $reservation->unit?->unit_number ?: 'وحدة';
            $propertyName = $reservation->unit?->property?->name_ar ?: $reservation->unit?->property?->name ?: '';
            $channel = $this->bookingSourceLabel($reservation->booking_source);
            $reference = $reservation->booking_reference ?: $reservation->id;
            $date = $this->dateValue($reservation->check_in_date) ?: $this->dateValue($reservation->created_at) ?: Carbon::now();
            $vatAmount = (float) ($reservation->vat_amount ?? 0);
            $serviceFee = (float) ($reservation->service_fee ?? 0);

            $transactions[] = [
                'transaction_key' => $date->format('Ymd') . '-income-' . $reservation->id,
                'date' => $date->toDateString(),
                'sort_date' => $date->timestamp,
                'type' => 'income',
                'type_label' => 'إيراد',
                'description' => trim("حجز - {$reservation->guest_name} - {$unitName} {$propertyName}"),
                'amount' => round((float) $reservation->total_amount, 2),
                'vat_amount' => round($vatAmount, 2),
                'channel' => $channel,
                'status' => $reservation->payment_status,
                'status_label' => $this->paymentStatusLabel($reservation->payment_status),
                'reference' => $reference,
            ];

            if ($serviceFee > 0) {
                $transactions[] = [
                    'transaction_key' => $date->format('Ymd') . '-commission-' . $reservation->id,
                    'date' => $date->toDateString(),
                    'sort_date' => $date->timestamp + 1,
                    'type' => 'commission',
                    'type_label' => 'عمولة',
                    'description' => trim("عمولة {$channel} - {$reference}"),
                    'amount' => round($serviceFee, 2),
                    'vat_amount' => round($serviceFee * 0.15, 2),
                    'channel' => $channel,
                    'status' => $reservation->payment_status === 'paid' ? 'completed' : 'pending',
                    'status_label' => $reservation->payment_status === 'paid' ? 'مكتمل' : 'معلق',
                    'reference' => $reference,
                ];
            }
        }

        foreach ($expenses as $expense) {
            $date = $this->dateValue($expense->expense_date) ?: $this->dateValue($expense->created_at) ?: Carbon::now();
            $channel = $this->paymentMethodLabel($expense->payment_method);
            $label = $expense->category?->name_ar ?: $expense->category?->name ?: 'مصروف';
            $description = trim($expense->description ?: $label);

            if ($expense->supplier) {
                $description = "{$description} - {$expense->supplier}";
            }

            $transactions[] = [
                'transaction_key' => $date->format('Ymd') . '-expense-' . $expense->id,
                'date' => $date->toDateString(),
                'sort_date' => $date->timestamp + 2,
                'type' => 'payment',
                'type_label' => 'دفعة',
                'description' => $description,
                'amount' => round((float) $expense->amount, 2),
                'vat_amount' => 0,
                'channel' => $channel,
                'status' => 'completed',
                'status_label' => 'مكتمل',
                'reference' => $expense->id,
            ];
        }

        usort($transactions, fn (array $first, array $second) => $second['sort_date'] <=> $first['sort_date']);

        return collect($transactions)
            ->values()
            ->map(function (array $transaction, int $index) {
                $transaction['transaction_no'] = sprintf('TXN-%s-%03d', substr($transaction['date'], 0, 4), $index + 1);
                unset($transaction['sort_date']);

                return $transaction;
            })
            ->all();
    }

    private function trendPayload(float $current, float $previous): array
    {
        $change = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100 : 0);

        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'change' => $change,
            'direction' => $change >= 0 ? 'up' : 'down',
        ];
    }

    private function bookingSourceLabel(?string $source): string
    {
        return match ($source) {
            'airbnb' => 'Airbnb',
            'booking_com' => 'Booking.com',
            'agoda' => 'Agoda',
            'vrbo' => 'VRBO',
            'direct' => 'مباشر',
            default => 'أخرى',
        };
    }

    private function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'cash' => 'نقدي',
            'card' => 'بطاقة',
            'bank_transfer' => 'تحويل بنكي',
            'check' => 'شيك',
            default => 'غير محدد',
        };
    }

    private function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'unpaid' => 'معلق',
            'partial' => 'جزئي',
            'paid' => 'مكتمل',
            'refunded' => 'مسترد',
            default => 'غير محدد',
        };
    }

    private function dateValue($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
    }

    private function unitStatuses(Request $request): array
    {
        $totalUnits = (clone $this->unitsFor($request))->count();
        $counts = $this->unitsFor($request)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $occupiedUnits = $this->occupiedUnitsCount($request);
        $maintenanceUnits = (int) ($counts['maintenance'] ?? 0);
        $cleaningUnits = (int) ($counts['cleaning'] ?? 0);
        $blockedUnits = (int) ($counts['blocked'] ?? 0);
        $availableUnits = max(0, $totalUnits - $occupiedUnits - $cleaningUnits - $maintenanceUnits - $blockedUnits);

        return [
            'available' => $availableUnits,
            'occupied' => $occupiedUnits,
            'cleaning' => $cleaningUnits,
            'maintenance' => $maintenanceUnits,
            'blocked' => $blockedUnits,
        ];
    }

    private function occupiedUnitsCount(Request $request): int
    {
        return $this->occupiedUnitIds($request)->count();
    }

    private function occupiedUnitIds(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $statusOccupiedIds = $this->unitsFor($request)
            ->where('status', 'occupied')
            ->pluck('id');
        $reservedOccupiedIds = $this->reservationsFor($request)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_in_date', '<=', $today)
            ->whereDate('check_out_date', '>=', $today)
            ->pluck('unit_id');

        return $statusOccupiedIds
            ->merge($reservedOccupiedIds)
            ->filter()
            ->unique()
            ->values();
    }

    private function monthlyRevenue(Request $request): array
    {
        return collect(range(6, 0))->map(function (int $monthsAgo) use ($request) {
            $date = Carbon::now()->subMonths($monthsAgo);
            $total = (clone $this->reservationsFor($request))
                ->whereBetween('check_in_date', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])
                ->sum('total_amount');

            return [
                'label' => $date->locale('ar')->translatedFormat('M'),
                'total' => (float) $total,
            ];
        })->values()->all();
    }

    private function bookingSources(Request $request): array
    {
        return $this->reservationsFor($request)
            ->selectRaw('booking_source, count(*) as total')
            ->groupBy('booking_source')
            ->pluck('total', 'booking_source')
            ->map(fn ($total, $source) => ['source' => $source, 'total' => (int) $total])
            ->values()
            ->all();
    }

    private function ownersFor(Request $request)
    {
        $query = User::query()
            ->where('role', 'owner')
            ->withCount(['ownedProperties', 'ownedUnits']);

        if (!$request->user()->isAdmin()) {
            $query->where(function (Builder $q) use ($request) {
                $q->whereHas('ownedProperties', fn (Builder $property) => $property->where('user_id', $request->user()->id))
                    ->orWhereHas('ownedUnits.property', fn (Builder $property) => $property->where('user_id', $request->user()->id));
            });
        }

        return $query->latest()->limit(10)->get(['id', 'full_name', 'email', 'phone', 'role', 'owner_reference_number', 'company_name', 'is_active']);
    }

    private function staffFor(Request $request, array $roles)
    {
        $query = User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true);

        if (!$request->user()->isAdmin()) {
            $query->where(function (Builder $builder) use ($request) {
                $builder->whereHas('housekeepingTasks.unit.property', fn (Builder $property) => $property->where('user_id', $request->user()->id))
                    ->orWhereHas('maintenanceTickets.unit.property', fn (Builder $property) => $property->where('user_id', $request->user()->id));
            });
        }

        return $query->orderBy('full_name')->get(['id', 'full_name', 'email', 'phone', 'role', 'is_active']);
    }

    private function messagesCount(Request $request): int
    {
        return $this->reservationsFor($request)
            ->whereHas('messages')
            ->withCount('messages')
            ->get()
            ->sum('messages_count');
    }
}
