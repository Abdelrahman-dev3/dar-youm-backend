<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'owner_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'address',
        'address_ar',
        'city',
        'city_ar',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'property_type',
        'total_units',
        'cover_image_url',
        'amenities',
        'status',
        'is_listed',
    ];

    protected $casts = [
        'amenities' => 'array',
        'is_listed' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    // Computed attributes
    public function getAvailableUnitsCountAttribute()
    {
        return $this->units()->where('status', 'available')->count();
    }

    public function getOccupancyRateAttribute()
    {
        $totalUnits = $this->units()->count();

        if ($totalUnits === 0) {
            return 0;
        }

        $today = Carbon::today()->toDateString();
        $occupiedUnitIds = $this->units()
            ->where('status', 'occupied')
            ->pluck('id')
            ->merge(
                $this->units()
                    ->whereHas('reservations', function ($query) use ($today) {
                        $query->whereIn('status', ['confirmed', 'checked_in'])
                            ->whereDate('check_in_date', '<=', $today)
                            ->whereDate('check_out_date', '>=', $today);
                    })
                    ->pluck('id')
            )
            ->unique();

        return round(($occupiedUnitIds->count() / $totalUnits) * 100, 2);
    }
}
