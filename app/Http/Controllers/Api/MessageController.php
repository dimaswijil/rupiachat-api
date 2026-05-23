<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class MessageController extends Controller
{
    // ── LOAD HISTORY PESAN ────────────────────────────────────
    public function index($roomId)
    {
        $userId = auth()->id();
        $room = Room::where('room_id', $roomId)->where('user_id', $userId)->first();
        $clearedAt = $room ? $room->last_cleared_at : null;

        $query = Message::where('room_id', $roomId);
        if ($clearedAt) {
            $query->where('created_at', '>', $clearedAt);
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($m) => [
                'id' => (string) $m->id,
                'sender_id' => (string) $m->sender_id,
                'text' => $m->text ?? '',
                'type' => $m->type,
                'amount' => $m->amount,
                'is_read' => (bool) $m->is_read,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $messages]);
    }

    // ── KIRIM PESAN ───────────────────────────────────────────
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => 'required|string',
            'sender_id' => 'required|string',
            'text' => 'required_if:type,text|string',
            'type' => 'required|in:text,payment,image,call',
            'amount' => 'required_if:type,payment|string',
            'image' => 'required_if:type,image|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $mediaUrl = null;
        $mediaType = null;
        $mediaName = null;
        $mediaSize = null;

        if ($request->hasFile('image') && $request->type == 'image') {
            $file = $request->file('image');
            $path = $file->store('messages', 'public');
            $mediaUrl = url('storage/' . $path);
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        }

        $message = Message::create([
            'room_id' => $request->room_id,
            'sender_id' => $request->sender_id,
            'text' => $mediaUrl ? $mediaUrl : $request->text,
            'type' => $request->type ?? 'text',
            'amount' => $request->amount,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'media_name' => $mediaName,
            'media_size' => $mediaSize,
        ]);

        $messageData = [
            'id' => (string) $message->id,
            'sender_id' => (string) $message->sender_id,
            'text' => $message->text ?? '',
            'type' => $message->type,
            'amount' => $message->amount,
            'is_read' => false,
            'created_at' => $message->created_at->toIso8601String(),
        ];

        event(new MessageSent($request->room_id, $messageData));

        // ── KIRIM PUSH NOTIFICATION (FCM) ──────────────────────
        $this->sendFcmNotification($request->room_id, $request->sender_id, $message);

        return response()->json(['message' => $messageData], 201);
    }

    // ── KIRIM FCM NOTIFICATION ────────────────────────────────
    private function sendFcmNotification(string $roomId, string $senderId, Message $message)
    {
        try {
            // Parse room_id (format: "1_2") untuk cari receiver
            $ids = explode('_', $roomId);
            $receiverId = ($ids[0] == $senderId) ? $ids[1] : $ids[0];

            $receiver = User::find($receiverId);
            if (!$receiver || !$receiver->fcm_token) return;

            $sender = User::find($senderId);
            $senderName = $sender ? $sender->name : 'Seseorang';

            // Tentukan teks preview notifikasi berdasarkan tipe pesan
            $previewText = $message->text ?? '[Media]';
            if ($message->type === 'image') {
                $previewText = '📷 Foto';
            } elseif ($message->type === 'payment') {
                $previewText = '💰 Pembayaran';
            } elseif ($message->type === 'call') {
                $previewText = '📞 Panggilan';
            }

            $credentialsPath = storage_path('app/firebase-credentials.json');
            if (!file_exists($credentialsPath)) {
                \Log::warning('Firebase credentials not found at: ' . $credentialsPath);
                return;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $fcmMessage = CloudMessage::new()
                ->withToken($receiver->fcm_token)
                ->withNotification(Notification::create(
                    $senderName,
                    $previewText
                ))
                ->withData([
                    'type' => 'chat_message',
                    'room_id' => $roomId,
                    'sender_id' => (string) $senderId,
                    'sender_name' => $senderName,
                    'sender_photo' => $sender->profile_photo ?? '',
                    'sender_email' => $sender->email ?? '',
                    'msg_type' => $message->type ?? 'text',
                ])
                ->withAndroidConfig([
                    'priority' => 'high',
                ]);

            $messaging->send($fcmMessage);
        } catch (\Exception $e) {
            \Log::error('FCM Error: ' . $e->getMessage());
        }
    }

    // ── TYPING INDICATOR ──────────────────────────────────────
    public function typing(Request $request)
    {
        $roomId = $request->room_id;
        $isTyping = $request->boolean('is_typing');
        $userId = auth()->id();

        if ($roomId && $userId) {
            event(new \App\Events\UserTyping($roomId, (string)$userId, $isTyping));
        }

        return response()->json(['status' => 'success']);
    }

    // ── MARK AS READ ──────────────────────────────────────────
    public function markAsRead(Request $request)
    {
        $roomId = $request->room_id;
        $currentUserId = auth()->id();

        Message::where('room_id', $roomId)
            ->where('sender_id', '!=', $currentUserId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        event(new \App\Events\MessageRead($roomId, (string)$currentUserId));

        return response()->json(['status' => 'success', 'message' => 'Pesan telah dibaca']);
    }

    // ── ARCHIVE / UNARCHIVE ───────────────────────────────────
    // FIX: method baru untuk handle POST /api/rooms/archive
    public function archive(Request $request)
    {
        $roomId = $request->room_id;
        $isArchived = $request->is_archived;
        $userId = auth()->id();

        // Upsert: kalau belum ada record → insert, kalau sudah ada → update
        Room::updateOrCreate(
            ['room_id' => $roomId, 'user_id' => $userId],
            ['is_archived' => $isArchived]
        );

        return response()->json(['status' => 'success']);
    }

    // ── PIN / UNPIN ──────────────────────────────────────────
    public function pin(Request $request)
    {
        $roomId = $request->room_id;
        $isPinned = $request->is_pinned;
        $userId = auth()->id();

        // Upsert: kalau belum ada record → insert, kalau sudah ada → update
        Room::updateOrCreate(
            ['room_id' => $roomId, 'user_id' => $userId],
            ['is_pinned' => $isPinned]
        );

        return response()->json(['status' => 'success']);
    }
    // ── DELETE ROOM / CLEAR CHAT ─────────────────────────────
    public function deleteRoom(Request $request)
    {
        $roomId = $request->room_id;
        $type = $request->type; // 'me' atau 'everyone'
        $userId = auth()->id();

        if ($type === 'everyone') {
            // Hapus fisik semua pesan untuk semua orang
            Message::where('room_id', $roomId)->delete();
            // Reset last_cleared_at untuk semua partisipan di room ini agar sinkron
            Room::where('room_id', $roomId)->update(['last_cleared_at' => null]);
        } else {
            // Hapus untuk saya saja (update timestamp filter)
            Room::updateOrCreate(
                ['room_id' => $roomId, 'user_id' => $userId],
                ['last_cleared_at' => now()]
            );
        }

        return response()->json(['status' => 'success']);
    }
}