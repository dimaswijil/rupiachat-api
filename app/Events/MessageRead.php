<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $roomId;
    public string $userId; // Siapa yang membaca

    public function __construct(string $roomId, string $userId)
    {
        $this->roomId = $roomId;
        $this->userId = $userId;
    }

    public function broadcastOn(): array
    {
        return [new Channel('chat.' . $this->roomId)];
    }

    public function broadcastAs(): string
    {
        return 'MessageRead';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
            'user_id' => $this->userId,
        ];
    }
}
