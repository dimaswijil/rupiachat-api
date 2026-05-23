<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $fillable = [
        'caller_id',
        'receiver_id',
        'group_id',
        'group_name',
        'channel_name',
        'type',
        'status',
        'duration',
    ];

    public function caller()
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
