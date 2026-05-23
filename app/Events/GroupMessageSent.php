<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// GroupMessageSent = event yang di-broadcast ke Pusher untuk pesan grup
// Setiap kali ada pesan baru di grup → Laravel trigger event ini
// → Pusher broadcast ke channel 'group.{groupId}'
// → Flutter semua member yang subscribe channel itu langsung terima
class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $groupId;
    public array $message;

    public function __construct(int $groupId, array $message)
    {
        $this->groupId = $groupId;
        $this->message = $message;
    }

    // Channel yang di-broadcast
    // Flutter subscribe ke 'group.{groupId}'
    public function broadcastOn(): Channel
    {
        return new Channel('group.' . $this->groupId);
    }

    // Nama event yang Flutter dengarkan
    public function broadcastAs(): string
    {
        return 'GroupMessageSent';
    }

    // Data yang dikirim ke Flutter
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
