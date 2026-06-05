<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_count',
        'check_in_date',
        'check_out_date',
        'nights',
        'price_per_night',
        'total_amount',
        'cleaning_fee',
        'service_fee',
        'vat_amount',
        'currency',
        'booking_source',
        'booking_reference',
        'status',
        'payment_status',
        'special_requests',
        'internal_notes',
        'checked_in_at',
        'checked_out_at',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'price_per_night' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cleaning_fee' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    // Relationships
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    // Computed attributes
    public function getTotalDaysAttribute()
    {
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, ['confirmed', 'checked_in']);
    }

    // Helper methods
    public function checkIn()
    {
        $this->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);
    }

    public function checkOut()
    {
        $this->update([
            'status' => 'checked_out',
            'checked_out_at' => now(),
        ]);
    }
}
