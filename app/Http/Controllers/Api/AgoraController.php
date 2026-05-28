<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Agora\RtcTokenBuilder2;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use App\Models\GroupMember;

class AgoraController extends Controller
{
    /**
     * Generate Agora RTC Token (v007 — AccessToken2)
     *
     * POST /api/agora/token
     *
     * Token v007 adalah format resmi terbaru Agora yang kompatibel
     * dengan agora_rtc_engine SDK 6.x di Flutter.
     * Format lama v006 akan ditolak diam-diam oleh SDK modern.
     */
    public function generateToken(Request $request)
    {
        $request->validate([
            'channel_name' => 'required|string|max:64',
            // FIXED: uid boleh '0' (string dari Flutter) — dikonversi ke int di bawah
            'uid'          => 'nullable',
        ]);

        $appId          = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERTIFICATE');

        if (!$appId || !$appCertificate) {
            return response()->json([
                'error' => 'Konfigurasi Agora belum di-set di server (.env)'
            ], 500);
        }

        $channelName = $request->channel_name;

        // FIXED: uid HARUS integer — Flutter joinChannel(uid: 0) pakai int
        // Jika Flutter kirim string '0', tetap valid karena (int)'0' === 0
        $uid = (int) $request->input('uid', 0);

        // Token expire: 1 jam (cukup untuk satu sesi call)
        // FIXED: privilege expire harus SAMA dengan token expire agar video publish tidak cut off
        $tokenExpire     = 3600;
        $privilegeExpire = 3600;

        try {
            // Generate token v007 menggunakan RtcTokenBuilder2
            // FIXED: pakai ROLE_PUBLISHER agar bisa publish audio + video
            $token = RtcTokenBuilder2::buildTokenWithUid(
                $appId,
                $appCertificate,
                $channelName,
                $uid,
                RtcTokenBuilder2::ROLE_PUBLISHER,
                $tokenExpire,
                $privilegeExpire
            );

            if (empty($token)) {
                \Log::error('Agora token kosong — cek App Certificate di .env');
                return response()->json(['error' => 'Gagal generate token'], 500);
            }

            \Log::info('Agora Token Generated (v007)', [
                'channel'        => $channelName,
                'uid'            => $uid,
                'token_prefix'   => substr($token, 0, 10),
                'token_length'   => strlen($token),
                'privilege_expire_ts' => time() + $privilegeExpire,
                'expire_at'      => now()->addSeconds($tokenExpire)->toDateTimeString(),
            ]);

            return response()->json([
                'token'        => $token,
                'channel_name' => $channelName,
                'uid'          => $uid,          // FIXED: kembalikan int, bukan string
                'expire_at'    => time() + $tokenExpire,
            ]);

        } catch (\Exception $e) {
            \Log::error('Agora Token Error: ' . $e->getMessage());
            return response()->json(['error' => 'Gagal generate token: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Initiate a Call (Signaling via FCM)
     *
     * POST /api/agora/call
     */
    public function initiateCall(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'channel_name' => 'required|string',
            'call_type' => 'required|in:voice,video', // voice / video
        ]);

        $caller = $request->user();
        $receiver = User::find($request->receiver_id);

        if (!$receiver || !$receiver->fcm_token) {
            return response()->json([
                'error' => 'User tujuan tidak bisa ditelepon (FCM token tidak ditemukan)'
            ], 404);
        }

        $credentialsPath = storage_path('app/firebase-credentials.json');
        if (!file_exists($credentialsPath)) {
            \Log::warning('Firebase credentials not found at: ' . $credentialsPath);
            return response()->json(['error' => 'Firebase credentials not found'], 500);
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            $fcmMessage = CloudMessage::new()
                ->withToken($receiver->fcm_token)
                ->withData([
                    'type' => 'incoming_call',
                    'call_type' => $request->call_type,
                    'channel_name' => $request->channel_name,
                    'caller_id' => (string) $caller->id,
                    'caller_name' => $caller->name,
                    'caller_photo' => $caller->profile_photo ?? '',
                ])
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high',
                    'ttl' => '0s', // Jangan dikirim kalau HP mati lama
                ]))
                ->withApnsConfig([
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ]);

            $messaging->send($fcmMessage);

            return response()->json([
                'status' => 'success',
                'message' => 'Panggilan sedang diteruskan'
            ]);

        } catch (\Exception $e) {
            \Log::error('FCM Error (Call): ' . $e->getMessage());
            return response()->json(['error' => 'Gagal meneruskan panggilan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Initiate a Group Call (Signaling via FCM ke semua member)
     *
     * POST /api/agora/group-call
     */
    public function initiateGroupCall(Request $request)
    {
        $request->validate([
            'group_id' => 'required|string',
            'channel_name' => 'required|string',
            'call_type' => 'required|in:voice,video',
            'group_name' => 'required|string',
        ]);

        $caller = $request->user();
        $groupId = $request->group_id;

        // Ambil semua member grup kecuali si penelepon
        $members = GroupMember::where('group_id', $groupId)
            ->where('user_id', '!=', $caller->id)
            ->with('user')
            ->get();

        $credentialsPath = storage_path('app/firebase-credentials.json');
        if (!file_exists($credentialsPath)) {
            \Log::warning('Firebase credentials not found at: ' . $credentialsPath);
            return response()->json(['error' => 'Firebase credentials not found'], 500);
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();
            $successCount = 0;

            foreach ($members as $member) {
                if ($member->user && $member->user->fcm_token) {
                    $fcmMessage = CloudMessage::new()
                        ->withToken($member->user->fcm_token)
                        ->withData([
                            'type' => 'incoming_group_call',
                            'call_type' => $request->call_type,
                            'channel_name' => $request->channel_name,
                            'group_id' => $groupId,
                            'group_name' => $request->group_name,
                            'caller_id' => (string) $caller->id,
                            'caller_name' => $caller->name,
                            'caller_photo' => $caller->profile_photo ?? '',
                        ])
                        ->withAndroidConfig(AndroidConfig::fromArray([
                            'priority' => 'high',
                            'ttl' => '0s', // Jangan dikirim kalau HP mati lama
                        ]));

                    try {
                        $messaging->send($fcmMessage);
                        $successCount++;
                    } catch (\Exception $e) {
                        \Log::error('FCM Group Call Error for User ' . $member->user->id . ': ' . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Panggilan diteruskan ke $successCount anggota"
            ]);

        } catch (\Exception $e) {
            \Log::error('FCM Error (Group Call): ' . $e->getMessage());
            return response()->json(['error' => 'Gagal meneruskan panggilan grup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send Call Signal (Cancel, Decline, Accept)
     *
     * POST /api/agora/signal
     */
    public function sendSignal(Request $request)
    {
        $request->validate([
            'target_id' => 'required|exists:users,id', // Kepada siapa sinyal dikirim
            'channel_name' => 'required|string',
            'signal_type' => 'required|in:cancel,decline,accept,end,request_video,accept_video,decline_video', 
        ]);

        $sender = $request->user();
        $target = User::find($request->target_id);

        if (!$target || !$target->fcm_token) {
            return response()->json(['error' => 'Target FCM token not found'], 404);
        }

        $credentialsPath = storage_path('app/firebase-credentials.json');
        if (!file_exists($credentialsPath)) {
            return response()->json(['error' => 'Firebase credentials not found'], 500);
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $messaging = $factory->createMessaging();

            // Kita TIDAK menyertakan block Notification() agar HP Android TIDAK memunculkan popup notif visual.
            // Kita hanya kirim Data Payload (Silent Push) yang akan ditangkap oleh background service Flutter
            // untuk mematikan ringtone / menutup layar calling.
            $fcmMessage = CloudMessage::new()
                ->withToken($target->fcm_token)
                ->withData([
                    'type' => 'call_signal',
                    'signal_type' => $request->signal_type, // cancel / decline / end
                    'channel_name' => $request->channel_name,
                    'sender_id' => (string) $sender->id,
                ])
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'priority' => 'high'
                ]));

            $messaging->send($fcmMessage);

            return response()->json([
                'status' => 'success',
                'message' => 'Sinyal berhasil dikirim'
            ]);

        } catch (\Exception $e) {
            \Log::error('FCM Error (Signal): ' . $e->getMessage());
            return response()->json(['error' => 'Gagal mengirim sinyal: ' . $e->getMessage()], 500);
        }
    }
}
