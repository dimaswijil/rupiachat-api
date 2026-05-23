<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'description',
        'photo',
        'creator_id',
    ];

    /**
     * Pembuat grup
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Semua member grup (via pivot group_members)
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'group_members')
                    ->withPivot('role', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Semua pesan di grup
     */
    public function messages()
    {
        return $this->hasMany(GroupMessage::class);
    }

    /**
     * Record member di grup
     */
    public function groupMembers()
    {
        return $this->hasMany(GroupMember::class);
    }
}
