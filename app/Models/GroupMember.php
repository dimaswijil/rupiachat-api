<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'role',
        'is_pinned',
        'joined_at',
        'last_cleared_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'last_cleared_at' => 'datetime',
    ];

    /**
     * Grup tempat member ini bergabung
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * User yang menjadi member
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
