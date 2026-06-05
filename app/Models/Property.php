<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
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

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    // Computed attributes
    public function getAvailableUnitsCountAttribute()
    {
        return $this->units()->where('status', 'available')->count();
    }

    public function getOccupancyRateAttribute()
    {
        if ($this->total_units === 0) return 0;
        $occupied = $this->units()->where('status', 'occupied')->count();
        return round(($occupied / $this->total_units) * 100, 2);
    }
}
