<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $roomId;
    public string $userId;
    public bool $isTyping;

    public function __construct(string $roomId, string $userId, bool $isTyping)
    {
        $this->roomId = $roomId;
        $this->userId = $userId;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('chat.' . $this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'UserTyping';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'is_typing' => $this->isTyping,
        ];
    }
}
