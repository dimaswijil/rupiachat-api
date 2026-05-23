<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['room_id', 'user_id', 'is_archived', 'is_pinned', 'last_cleared_at'];
}