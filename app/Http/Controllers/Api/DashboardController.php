<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceTicket;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
        $unitCount = (clone $units)->count();
        $occupiedUnits = $this->occupiedUnitsCount($request);
        $totalRevenue = (float) $reservationRows->sum('total_amount');
        $averageDailyRate = $reservationRows->count() > 0 ? round((float) $reservationRows->avg('price_per_night'), 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'total_revenue' => $totalRevenue,
                    'occupancy_rate' => $unitCount > 0 ? round(($occupiedUnits / $unitCount) * 100, 1) : 0,
                    'average_daily_rate' => $averageDailyRate,
                    'revpar' => $unitCount > 0 ? round($totalRevenue / $unitCount, 2) : 0,
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
                    'paid_revenue' => (float) $reservationRows->where('payment_status', 'paid')->sum('total_amount'),
                    'unpaid_revenue' => (float) $reservationRows->whereIn('payment_status', ['unpaid', 'partial'])->sum('total_amount'),
                    'service_fees' => (float) $reservationRows->sum('service_fee'),
                    'vat_amount' => (float) $reservationRows->sum('vat_amount'),
                    'cleaning_fees' => (float) $reservationRows->sum('cleaning_fee'),
                ],
                'owners' => $user->hasPermission('canViewOwners') ? $this->ownersFor($request) : [],
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

        return $query->latest()->limit(10)->get(['id', 'full_name', 'email', 'phone', 'role', 'company_name', 'is_active']);
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
