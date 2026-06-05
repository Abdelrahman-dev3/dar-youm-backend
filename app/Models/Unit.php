<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'property_id',
        'owner_id',
        'unit_number',
        'unit_name',
        'unit_name_ar',
        'unit_type',
        'bedrooms',
        'bathrooms',
        'size_sqm',
        'max_guests',
        'base_price',
        'currency',
        'status',
        'floor_number',
        'amenities',
        'images',
        'cleaning_notes',
        'maintenance_notes',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'base_price' => 'decimal:2',
        'size_sqm' => 'decimal:2',
    ];

    // Relationships
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function maintenanceTickets()
    {
        return $this->hasMany(MaintenanceTicket::class);
    }

    // Helper methods
    public function getCurrentReservation()
    {
        return $this->reservations()
            ->where('status', 'checked_in')
            ->whereDate('check_in_date', '<=', now())
            ->whereDate('check_out_date', '>=', now())
            ->first();
    }
}
