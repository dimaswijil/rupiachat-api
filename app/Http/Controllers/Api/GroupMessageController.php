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
                    'text'        => $m->text ?? '',
                    'type'        => $m->type,
                    'amount'      => $m->amount,
                    'media_url'   => $m->media_url,
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
            'type'   => 'required|in:text,payment,image,call',
            'amount' => 'required_if:type,payment|string',
            'image'  => 'required_if:type,image|image|mimes:jpeg,png,jpg,gif|max:5120',
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
            $mediaUrl = url('storage/' . $path);
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
            'text'        => $message->text ?? '',
            'type'        => $message->type,
            'amount'      => $message->amount,
            'media_url'   => $message->media_url,
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
