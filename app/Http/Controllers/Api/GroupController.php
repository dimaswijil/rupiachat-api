<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{
    // ── DAFTAR SEMUA GRUP USER ────────────────────────────────
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $groups = Group::whereHas('groupMembers', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['creator:id,name,profile_photo', 'groupMembers'])
        ->get()
        ->map(function (Group $group) use ($userId) {
            // Ambil pesan terakhir di grup
            $lastMsg = $group->messages()->orderBy('created_at', 'desc')->first();

            // Hitung jumlah member
            $memberCount = $group->groupMembers->count();

            // Role user saat ini
            $myMembership = $group->groupMembers->where('user_id', $userId)->first();

            return [
                'id'              => (string) $group->id,
                'name'            => $group->name,
                'description'     => $group->description,
                'photo'           => $group->photo,
                'creator_id'      => (string) $group->creator_id,
                'creator_name'    => $group->creator->name ?? null,
                'member_count'    => $memberCount,
                'my_role'         => $myMembership ? $myMembership->role : null,
                'is_pinned'       => $myMembership ? (bool)$myMembership->is_pinned : false,
                'last_message'    => $lastMsg ? ($lastMsg->text ?? '') : null,
                'last_message_time' => $lastMsg ? $lastMsg->created_at->toIso8601String() : null,
                'created_at'      => $group->created_at->toIso8601String(),
            ];
        })
        ->sortByDesc('last_message_time')
        ->values();

        return response()->json(['data' => $groups]);
    }

    // ── BUAT GRUP BARU ───────────────────────────────────────
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:255',
            'description'=> 'nullable|string|max:500',
            'member_ids' => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $currentUser = $request->user();

        // Buat grup
        $group = Group::create([
            'name'        => $request->name,
            'description' => $request->description,
            'creator_id'  => $currentUser->id,
        ]);

        // Tambah creator sebagai admin
        GroupMember::create([
            'group_id'  => $group->id,
            'user_id'   => $currentUser->id,
            'role'      => 'admin',
            'joined_at' => now(),
        ]);

        // Tambah member lainnya
        $memberIds = collect($request->member_ids)->unique()->filter(fn($id) => $id != $currentUser->id);
        foreach ($memberIds as $memberId) {
            GroupMember::create([
                'group_id'  => $group->id,
                'user_id'   => $memberId,
                'role'      => 'member',
                'joined_at' => now(),
            ]);
        }

        // Load data lengkap
        $group->load('creator:id,name,profile_photo', 'members:id,name,email,phone,profile_photo,is_online');

        return response()->json([
            'message' => 'Grup berhasil dibuat',
            'data'    => [
                'id'          => (string) $group->id,
                'name'        => $group->name,
                'description' => $group->description,
                'photo'       => $group->photo,
                'creator_id'  => (string) $group->creator_id,
                'creator_name'=> $group->creator->name,
                'members'     => $group->members->map(fn($m) => [
                    'id'        => (string) $m->id,
                    'name'      => $m->name,
                    'email'     => $m->email,
                    'phone'     => $m->phone,
                    'photo_url' => $m->profile_photo,
                    'is_online' => (bool) $m->is_online,
                    'role'      => $m->pivot->role,
                ]),
                'member_count'=> $group->members->count(),
                'created_at'  => $group->created_at->toIso8601String(),
            ],
        ], 201);
    }

    // ── DETAIL GRUP ──────────────────────────────────────────
    public function show(Request $request, $id)
    {
        $group = Group::with('creator:id,name,profile_photo', 'members:id,name,email,phone,profile_photo,is_online')
            ->find($id);

        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah member
        $isMember = $group->members->contains('id', $request->user()->id);
        if (!$isMember) {
            return response()->json(['message' => 'Anda bukan member grup ini'], 403);
        }

        $myMembership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'data' => [
                'id'          => (string) $group->id,
                'name'        => $group->name,
                'description' => $group->description,
                'photo'       => $group->photo,
                'creator_id'  => (string) $group->creator_id,
                'creator_name'=> $group->creator->name ?? null,
                'my_role'     => $myMembership ? $myMembership->role : null,
                'is_pinned'   => $myMembership ? (bool)$myMembership->is_pinned : false,
                'members'     => $group->members->map(fn($m) => [
                    'id'        => (string) $m->id,
                    'name'      => $m->name,
                    'email'     => $m->email,
                    'phone'     => $m->phone,
                    'photo_url' => $m->profile_photo,
                    'is_online' => (bool) $m->is_online,
                    'role'      => $m->pivot->role,
                ]),
                'member_count'=> $group->members->count(),
                'created_at'  => $group->created_at->toIso8601String(),
            ],
        ]);
    }

    // ── UPDATE GRUP ──────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah admin
        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa mengubah grup'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->has('name')) $group->name = $request->name;
        if ($request->has('description')) $group->description = $request->description;
        $group->save();

        return response()->json([
            'message' => 'Grup berhasil diperbarui',
            'data'    => [
                'id'          => (string) $group->id,
                'name'        => $group->name,
                'description' => $group->description,
                'photo'       => $group->photo,
            ],
        ]);
    }

    // ── TAMBAH MEMBER ────────────────────────────────────────
    public function addMembers(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah admin
        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa menambah member'], 403);
        }

        $validator = Validator::make($request->all(), [
            'member_ids'   => 'required|array|min:1',
            'member_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $added = [];
        foreach ($request->member_ids as $memberId) {
            // Skip jika sudah ada
            $exists = GroupMember::where('group_id', $id)
                ->where('user_id', $memberId)
                ->exists();

            if (!$exists) {
                GroupMember::create([
                    'group_id'  => $id,
                    'user_id'   => $memberId,
                    'role'      => 'member',
                    'joined_at' => now(),
                ]);
                $added[] = $memberId;
            }
        }

        $group->load('members:id,name,email,phone,profile_photo,is_online');

        return response()->json([
            'message'      => count($added) . ' member berhasil ditambahkan',
            'added_ids'    => $added,
            'member_count' => $group->members->count(),
            'members'      => $group->members->map(fn($m) => [
                'id'        => (string) $m->id,
                'name'      => $m->name,
                'email'     => $m->email,
                'phone'     => $m->phone,
                'photo_url' => $m->profile_photo,
                'is_online' => (bool) $m->is_online,
                'role'      => $m->pivot->role,
            ]),
        ]);
    }

    // ── HAPUS MEMBER ─────────────────────────────────────────
    public function removeMember(Request $request, $id, $userId)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah admin
        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa menghapus member'], 403);
        }

        // Tidak bisa hapus diri sendiri via endpoint ini (gunakan /leave)
        if ($userId == $request->user()->id) {
            return response()->json(['message' => 'Gunakan endpoint /leave untuk keluar dari grup'], 400);
        }

        // Tidak bisa hapus creator
        if ($userId == $group->creator_id) {
            return response()->json(['message' => 'Tidak bisa menghapus pembuat grup'], 400);
        }

        $deleted = GroupMember::where('group_id', $id)
            ->where('user_id', $userId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'User bukan member grup ini'], 404);
        }

        return response()->json([
            'message'      => 'Member berhasil dihapus',
            'member_count' => GroupMember::where('group_id', $id)->count(),
        ]);
    }

    // ── JADIKAN ADMIN ────────────────────────────────────────
    public function makeAdmin(Request $request, $id, $userId)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa menjadikan admin'], 403);
        }

        $target = GroupMember::where('group_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$target) {
            return response()->json(['message' => 'User bukan member grup ini'], 404);
        }

        $target->update(['role' => 'admin']);

        return response()->json(['message' => 'Berhasil menjadikan admin']);
    }

    // ── HAPUS ADMIN (kembali jadi member) ────────────────────
    public function removeAdmin(Request $request, $id, $userId)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa mengubah role'], 403);
        }

        if ($userId == $group->creator_id) {
            return response()->json(['message' => 'Tidak bisa mengubah role pembuat grup'], 400);
        }

        $target = GroupMember::where('group_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$target) {
            return response()->json(['message' => 'User bukan member grup ini'], 404);
        }

        $target->update(['role' => 'member']);

        return response()->json(['message' => 'Berhasil menghapus role admin']);
    }

    // ── KELUAR DARI GRUP ─────────────────────────────────────
    public function leave(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        $userId = $request->user()->id;

        // Jika creator keluar, transfer admin ke member tertua
        if ($group->creator_id == $userId) {
            $nextAdmin = GroupMember::where('group_id', $id)
                ->where('user_id', '!=', $userId)
                ->orderBy('joined_at', 'asc')
                ->first();

            if ($nextAdmin) {
                $nextAdmin->update(['role' => 'admin']);
                $group->update(['creator_id' => $nextAdmin->user_id]);
            } else {
                // Satu-satunya member → hapus grup
                $group->delete();
                return response()->json(['message' => 'Grup dihapus karena tidak ada member lain']);
            }
        }

        GroupMember::where('group_id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['message' => 'Berhasil keluar dari grup']);
    }

    // ── HAPUS GRUP ───────────────────────────────────────────
    public function destroy(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Hanya creator/admin yang bisa hapus grup
        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa menghapus grup'], 403);
        }

        // Hapus grup (cascade ke group_members & group_messages)
        $group->delete();

        return response()->json(['message' => 'Grup berhasil dihapus']);
    }

    // ── UPDATE FOTO GRUP ─────────────────────────────────────
    public function updatePhoto(Request $request, $id)
    {
        $group = Group::find($id);
        if (!$group) {
            return response()->json(['message' => 'Grup tidak ditemukan'], 404);
        }

        // Cek apakah user adalah admin
        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership || $membership->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang bisa mengubah foto grup'], 403);
        }

        $request->validate(['photo' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120']);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('groups', 'public');
            $group->photo = asset('storage/' . $path);
            $group->save();

            return response()->json([
                'message'   => 'Foto grup berhasil diupdate',
                'photo_url' => $group->photo,
            ]);
        }

        return response()->json(['message' => 'Tidak ada foto yang diupload'], 400);
    }

    // ── PIN / UNPIN GRUP ─────────────────────────────────────
    public function pinGroup(Request $request, $id)
    {
        $membership = GroupMember::where('group_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$membership) {
            return response()->json(['message' => 'Anda bukan member grup ini'], 403);
        }

        $membership->update([
            'is_pinned' => $request->is_pinned ? true : false,
        ]);

        return response()->json([
            'status'    => 'success',
            'is_pinned' => (bool) $membership->is_pinned,
        ]);
    }
}
