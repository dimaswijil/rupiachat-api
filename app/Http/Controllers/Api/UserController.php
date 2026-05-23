<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Room;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // ── DAFTAR SEMUA USER ─────────────────────────────────────
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $cid = $currentUser->id;

        $roomConcat = "CONCAT(LEAST(CAST(users.id AS CHAR), CAST({$cid} AS CHAR)), '_', GREATEST(CAST(users.id AS CHAR), CAST({$cid} AS CHAR)))";

        $users = User::where('users.id', '!=', $cid)
            ->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.is_online', 'users.profile_photo')
            ->leftJoin('rooms as pin_rooms', function ($join) use ($roomConcat, $cid) {
                $join->on(\DB::raw($roomConcat), '=', 'pin_rooms.room_id')
                     ->where('pin_rooms.user_id', '=', $cid);
            })
            ->addSelect(\DB::raw('COALESCE(pin_rooms.is_pinned, 0) as is_pinned_raw'))
            ->addSelect(\DB::raw('pin_rooms.last_cleared_at as room_cleared_at'))
            ->get()
            ->map(function ($u) use ($currentUser) {
                $ids = [(string) $currentUser->id, (string) $u->id];
                sort($ids, SORT_STRING);
                $roomId = implode('_', $ids);

                $clearedAt = $u->room_cleared_at;

                // Ambil pesan terakhir yang LEBIH BARU dari waktu pembersihan
                $lastMsgQuery = \DB::table('messages')
                    ->where('room_id', $roomId);
                
                if ($clearedAt) {
                    $lastMsgQuery->where('created_at', '>', $clearedAt);
                }

                $lastMsg = $lastMsgQuery->orderBy('created_at', 'desc')->first();

                // Hitung unread yang LEBIH BARU dari waktu pembersihan
                $unreadQuery = \DB::table('messages')
                    ->where('room_id', $roomId)
                    ->where('sender_id', '!=', $currentUser->id)
                    ->where('is_read', 0);
                
                if ($clearedAt) {
                    $unreadQuery->where('created_at', '>', $clearedAt);
                }

                $unreadCount = $unreadQuery->count();

                // Room info tambahan
                $room = \DB::table('rooms')
                    ->where('room_id', $roomId)
                    ->where('user_id', $currentUser->id)
                    ->first();

                return [
                    'id' => (string) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'photo_url' => $u->profile_photo,
                    'is_online' => (bool) $u->is_online,
                    'is_archived' => $room ? (bool) $room->is_archived : false,
                    'is_pinned' => $room ? (bool) $room->is_pinned : false,
                    'last_message' => $lastMsg ? ($lastMsg->text ?? '') : null,
                    'last_message_time' => $lastMsg ? $lastMsg->created_at : null,
                    'unread_count' => $unreadCount,
                    'room_cleared_at' => $clearedAt,
                ];
            })
            ->filter(function($item) {
                // Tampilkan jika: ada pesan OR belum pernah dihapus OR di-pin OR di-arsip
                return $item['last_message'] !== null || $item['room_cleared_at'] === null || $item['is_pinned'] || $item['is_archived'];
            })
            ->sort(function($a, $b) {
                // 1. Prioritas Pin
                if ($a['is_pinned'] && !$b['is_pinned']) return -1;
                if (!$a['is_pinned'] && $b['is_pinned']) return 1;

                // 2. Prioritas Waktu Pesan Terakhir
                $timeA = $a['last_message_time'] ?? '1000-01-01 00:00:00';
                $timeB = $b['last_message_time'] ?? '1000-01-01 00:00:00';
                
                return strcmp($timeB, $timeA); // Descending (Terbaru di atas)
            })
            ->values();

        return response()->json(['data' => $users]);
    }

    // ── SEARCH BY PHONE ───────────────────────────────────────
    public function search(Request $request)
    {
        $phone = $request->query('phone');
        if (!$phone) {
            return response()->json(['message' => 'Phone parameter is required'], 400);
        }

        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'photo_url' => $user->profile_photo,
                'is_online' => (bool) $user->is_online,
            ]
        ]);
    }

    public function updatePhoto(Request $request)
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120']);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            $user = $request->user();

            // Build URL dynamically from the current request
            $baseUrl = $request->getSchemeAndHttpHost();
            $photoUrl = $baseUrl . '/storage/' . $path;

            $user->profile_photo = $photoUrl;
            $user->save();

            return response()->json([
                'message' => 'Foto berhasil diupdate',
                'photo_url' => $user->profile_photo
            ]);
        }

        return response()->json(['message' => 'Tidak ada foto yang diupload'], 400);
    }

    // ── UPDATE PROFILE ─────────────────────────────────────────
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'photo_url' => $user->profile_photo,
            ]
        ]);
    }
}