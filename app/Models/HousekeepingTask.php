<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HousekeepingTask extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'reservation_id',
        'assigned_to',
        'task_type',
        'priority',
        'status',
        'scheduled_date',
        'scheduled_time',
        'started_at',
        'completed_at',
        'estimated_duration_minutes',
        'checklist',
        'before_photos',
        'after_photos',
        'notes',
        'issues_found',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'checklist' => 'array',
        'before_photos' => 'array',
        'after_photos' => 'array',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
