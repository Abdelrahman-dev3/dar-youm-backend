<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'reservation_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'channel',
        'direction',
        'message_content',
        'is_read',
        'requires_action',
        'ai_suggested_reply',
        'read_at',
        'replied_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'requires_action' => 'boolean',
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
