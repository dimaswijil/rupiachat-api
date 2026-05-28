<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupMessage;
use App\Models\User;
use App\Events\GroupMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class GroupMessageController extends Controller
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

    // ── LOAD HISTORY PESAN GRUP ──────────────────────────────
    public function index(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah member
        $isMember = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Anda bukan member grup ini'], 403);
        }

        $messages = GroupMessage::where('group_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($m) {
                $sender = User::find($m->sender_id);
                return [
                    'id'          => (string) $m->id,
                    'group_id'    => (string) $m->group_id,
                    'sender_id'   => (string) $m->sender_id,
                    'sender_name' => $sender ? $sender->name : 'Unknown',
                    'sender_photo'=> $sender ? $sender->profile_photo : null,
                    'text'        => in_array($m->type, ['image', 'audio', 'pdf', 'document']) ? $this->getMediaUrl($m->text) : ($m->text ?? ''),
                    'type'        => $m->type,
                    'amount'      => $m->amount,
                    'media_url'   => $m->media_url ? $this->getMediaUrl($m->media_url) : null,
                    'media_type'  => $m->media_type,
                    'media_name'  => $m->media_name,
                    'media_size'  => $m->media_size,
                    'created_at'  => $m->created_at->toIso8601String(),
                ];
            });

        return response()->json(['data' => $messages]);
    }

    // ── KIRIM PESAN KE GRUP ──────────────────────────────────
    public function store(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah member
        $isMember = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Anda bukan member grup ini'], 403);
        }

        $validator = Validator::make($request->all(), [
            'text'   => 'required_if:type,text|string',
            'type'   => 'required|in:text,payment,image,call,sticker,audio,pdf',
            'amount' => 'required_if:type,payment|string',
            'image'  => 'required_if:type,image|image|mimes:jpeg,png,jpg,gif|max:5120',
            'audio'  => 'required_if:type,audio|file|mimes:m4a,mp3,aac,wav,ogg|max:10240',
            'document' => 'required_if:type,pdf|file|mimes:pdf|max:10240',
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
            $path = $file->store('group_messages', 'public');
            $mediaUrl = 'storage/' . $path;
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        } elseif ($request->hasFile('audio') && $request->type == 'audio') {
            $file = $request->file('audio');
            $path = $file->store('group_messages_audio', 'public');
            $mediaUrl = 'storage/' . $path;
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        } elseif ($request->hasFile('document') && $request->type == 'pdf') {
            $file = $request->file('document');
            $path = $file->store('group_messages_documents', 'public');
            $mediaUrl = 'storage/' . $path;
            $mediaType = $file->getMimeType();
            $mediaName = $file->getClientOriginalName();
            $mediaSize = $file->getSize();
        }

        $currentUser = $request->user();

        $message = GroupMessage::create([
            'group_id'   => $id,
            'sender_id'  => $currentUser->id,
            'text'       => $mediaUrl ? $mediaUrl : $request->text,
            'type'       => $request->type ?? 'text',
            'amount'     => $request->amount,
            'media_url'  => $mediaUrl,
            'media_type' => $mediaType,
            'media_name' => $mediaName,
            'media_size' => $mediaSize,
        ]);

        $messageData = [
            'id'          => (string) $message->id,
            'group_id'    => (string) $message->group_id,
            'sender_id'   => (string) $message->sender_id,
            'sender_name' => $currentUser->name,
            'sender_photo'=> $currentUser->profile_photo,
            'text'        => in_array($message->type, ['image', 'audio', 'pdf', 'document']) ? $this->getMediaUrl($message->text) : ($message->text ?? ''),
            'type'        => $message->type,
            'amount'      => $message->amount,
            'media_url'   => $message->media_url ? $this->getMediaUrl($message->media_url) : null,
            'media_type'  => $message->media_type,
            'media_name'  => $message->media_name,
            'media_size'  => $message->media_size,
            'created_at'  => $message->created_at->toIso8601String(),
        ];

        // Broadcast ke Pusher
        event(new GroupMessageSent((int) $id, $messageData));

        // Kirim FCM ke semua member kecuali sender
        $this->sendGroupFcmNotification($group, $currentUser, $message);

        return response()->json(['message' => $messageData], 201);
    }

    // ── KIRIM FCM KE SEMUA MEMBER GRUP ───────────────────────
    private function sendGroupFcmNotification(Group $group, User $sender, GroupMessage $message)
    {
        try {
            $credentialsPath = storage_path('app/firebase-credentials.json');
            if (!file_exists($credentialsPath)) {
                \Log::warning('Firebase credentials not found at: ' . $credentialsPath);
                return;
            }

            // Tentukan teks preview
            $previewText = $message->text ?? '[Media]';
            if ($message->type === 'image') {
                $previewText = '📷 Foto';
            } elseif ($message->type === 'payment') {
                $previewText = '💰 Pembayaran';
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            // Ambil semua member kecuali sender yang punya FCM token
            $members = GroupMember::where('group_id', $group->id)
                ->where('user_id', '!=', $sender->id)
                ->get();

            foreach ($members as $member) {
                $user = User::find($member->user_id);
                if (!$user || !$user->fcm_token) continue;

                $fcmMessage = CloudMessage::new()
                    ->withToken($user->fcm_token)
                    ->withNotification(Notification::create(
                        $group->name . ' - ' . $sender->name,
                        $previewText
                    ))
                    ->withData([
                        'type'         => 'group_message',
                        'group_id'     => (string) $group->id,
                        'group_name'   => $group->name,
                        'group_photo'  => $group->photo ?? '',
                        'sender_id'    => (string) $sender->id,
                        'sender_name'  => $sender->name,
                        'sender_photo' => $sender->profile_photo ?? '',
                        'msg_type'     => $message->type,
                    ])
                    ->withAndroidConfig([
                        'priority' => 'high',
                    ]);

                $messaging->send($fcmMessage);
            }
        } catch (\Exception $e) {
            \Log::error('Group FCM Error: ' . $e->getMessage());
        }
    }
}
