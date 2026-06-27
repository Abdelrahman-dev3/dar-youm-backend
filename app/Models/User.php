<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'role',
        'avatar_url',
        'company_name',
        'language_preference',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    // Relationships
    public function properties()
    {
        return $this->hasMany(Property::class);
    }

    public function ownedProperties()
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    public function ownedUnits()
    {
        return $this->hasMany(Unit::class, 'owner_id');
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class, 'assigned_to');
    }

    public function maintenanceTickets()
    {
        return $this->hasMany(MaintenanceTicket::class, 'assigned_to');
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isPropertyManager()
    {
        return $this->role === 'property_manager';
    }

    public function isOwner()
    {
        return $this->role === 'owner';
    }

    public function permissions(): array
    {
        return config("role_permissions.{$this->role}", config('role_permissions.property_manager', []));
    }

    public function hasPermission(string $permission): bool
    {
        return (bool) ($this->permissions()[$permission] ?? false);
    }
}
