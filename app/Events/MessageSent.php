<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

// MessageSent = event yang di-broadcast ke Pusher
// Setiap kali ada pesan baru → Laravel trigger event ini
// → Pusher broadcast ke channel 'chat.{roomId}'
// → Flutter User B yang subscribe channel itu langsung terima
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $roomId;
    public array $message;

    public function __construct(string $roomId, array $message)
    {
        $this->roomId = $roomId;
        $this->message = $message;
    }

    // Channel yang di-broadcast
    // Flutter subscribe ke 'chat.{roomId}' untuk layar pesan, dan 'user.{uid}' untuk daftar chat
    public function broadcastOn(): array
    {
        $channels = [new Channel('chat.' . $this->roomId)];
        
        if (str_contains($this->roomId, '_')) {
            $ids = explode('_', $this->roomId);
            if (count($ids) >= 2) {
                $channels[] = new Channel('user.' . $ids[0]);
                $channels[] = new Channel('user.' . $ids[1]);
            }
        }
        
        return $channels;
    }

    // Nama event yang Flutter dengarkan
    // Di chat_service.dart: event.eventName == 'App\\Events\\MessageSent'
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    // Data yang dikirim ke Flutter
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}