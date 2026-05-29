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
    private function getMediaUrl($text)
    {
        if (empty($text)) return '';
        if (str_starts_with($text, 'http://') || str_starts_with($text, 'https://')) {
            if (preg_match('/storage\/(messages|group_messages|messages_audio|group_messages_audio|group_messages_documents|messages_documents)\/(.+)$/', $text, $matches)) {
                return url('storage/' . $matches[1] . '/' . $matches[2]);
            }
            return $text;
        }
        return url($text);
    }

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
                'text' => in_array($m->type, ['image', 'audio', 'pdf', 'document']) ? $this->getMediaUrl($m->media_url ?? $m->text) : ($m->text ?? ''),
                'type' => $m->type,
                'amount' => $m->amount,
                'media_url' => $m->media_url ? $this->getMediaUrl($m->media_url) : null,
                'media_type' => $m->media_type,
                'media_name' => $m->media_name,
                'media_size' => $m->media_size,
                'is_read' => (bool) $m->is_read,
                'created_at' => $m->created_at->toIso8601String(),
                'caption' => in_array($m->type, ['image', 'audio', 'pdf']) && $m->media_url && $m->text !== $m->media_url && !in_array($m->text, ['[Gambar]', '[Dokumen PDF]', '[Audio]']) ? $m->text : null,
            ]);

        return response()->json(['data' => $messages]);
    }

    // ── KIRIM PESAN ───────────────────────────────────────────
    public function store(Request $request)
    {
        $rules = [
            'room_id' => 'required|string',
            'sender_id' => 'required|string',
            'text' => 'required_if:type,text|string',
            'type' => 'required|in:text,payment,image,call,sticker,audio,pdf',
            'amount' => 'required_if:type,payment|string',
            'image' => 'required_if:type,image|image|mimes:jpeg,png,jpg,gif|max:5120',
            'audio' => 'required_if:type,audio|file|mimes:m4a,mp3,aac,wav,ogg|max:10240',
            'document' => 'required_if:type,pdf|file|mimes:pdf|max:10240',
        ];

        // Jika klien mengirim URL publik secara langsung (misal Supabase Storage), hilangkan validasi file biner wajib
        if ($request->filled('text') && in_array($request->type, ['image', 'audio', 'pdf'])) {
            unset($rules['image']);
            unset($rules['audio']);
            unset($rules['document']);
        }

        $validator = Validator::make($request->all(), $rules);

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
            $mediaUrl = 'storage/' . $path;
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        } elseif ($request->hasFile('audio') && $request->type == 'audio') {
            $file = $request->file('audio');
            $path = $file->store('messages_audio', 'public');
            $mediaUrl = 'storage/' . $path;
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        } elseif ($request->hasFile('document') && $request->type == 'pdf') {
            $file = $request->file('document');
            $path = $file->store('messages_documents', 'public');
            $mediaUrl = 'storage/' . $path;
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        }

        // Sinkronisasi data media dari URL jika diunggah via Supabase Storage
        if (!$mediaUrl && $request->filled('text') && in_array($request->type, ['image', 'audio', 'pdf'])) {
            $mediaUrl = $request->text;
            $mediaType = $request->type == 'pdf' ? 'application/pdf' : ($request->type == 'audio' ? 'audio/mpeg' : 'image/jpeg');
            $mediaName = basename(parse_url($request->text, PHP_URL_PATH) ?? $request->text);
        }

        $message = Message::create([
            'room_id' => $request->room_id,
            'sender_id' => $request->sender_id,
            'text' => $request->filled('caption') ? $request->caption : ($mediaUrl ? $mediaUrl : $request->text),
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
            'text' => in_array($message->type, ['image', 'audio', 'pdf', 'document']) ? $this->getMediaUrl($message->media_url ?? $message->text) : ($message->text ?? ''),
            'type' => $message->type,
            'amount' => $message->amount,
            'media_url' => $message->media_url ? $this->getMediaUrl($message->media_url) : null,
            'media_type' => $message->media_type,
            'media_name' => $message->media_name,
            'media_size' => $message->media_size,
            'is_read' => false,
            'created_at' => $message->created_at->toIso8601String(),
            'caption' => in_array($message->type, ['image', 'audio', 'pdf']) && $message->media_url && $message->text !== $message->media_url && !in_array($message->text, ['[Gambar]', '[Dokumen PDF]', '[Audio]']) ? $message->text : null,
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
                $callData = json_decode($message->text, true);
                if (is_array($callData)) {
                    $isVideo = ($callData['call_type'] ?? '') === 'video';
                    $status = $callData['status'] ?? '';
                    if ($status === 'missed' || $status === 'declined') {
                        $previewText = $isVideo ? '📹 Panggilan Video Tak Terjawab' : '📞 Panggilan Suara Tak Terjawab';
                    } elseif ($status === 'answered') {
                        $previewText = $isVideo ? '📹 Panggilan Video Selesai' : '📞 Panggilan Suara Selesai';
                    }
                }
            } elseif ($message->type === 'pdf') {
                $previewText = '📄 Dokumen PDF';
            } elseif ($message->type === 'audio') {
                $previewText = '🎵 Pesan Suara';
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