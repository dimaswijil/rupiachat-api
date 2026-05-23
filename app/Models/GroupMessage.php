<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMessage extends Model
{
    protected $fillable = [
        'group_id',
        'sender_id',
        'text',
        'type',
        'amount',
        'media_url',
        'media_type',
        'media_name',
        'media_size',
    ];

    /**
     * Grup tempat pesan ini berada
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Pengirim pesan
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
