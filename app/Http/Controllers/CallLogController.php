<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    // GET /api/call-logs
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->id;

            $logs = CallLog::where('caller_id', $userId)
                ->orWhere('receiver_id', $userId)
                // Also include group calls where user is a member
                ->orWhereIn('group_id', function ($query) use ($userId) {
                    $query->select('group_id')
                        ->from('group_members')
                        ->where('user_id', $userId);
                })
                ->with(['caller:id,name,profile_photo,phone', 'receiver:id,name,profile_photo,phone'])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(function ($log) use ($userId) {
                    $isGroupCall = !empty($log->group_id);
                    $isCaller = $log->caller_id == $userId;
                    $otherUser = $isCaller ? $log->receiver : $log->caller;

                    $result = [
                        'id' => $log->id,
                        'is_group' => $isGroupCall,
                        'group_id' => $log->group_id,
                        'group_name' => $log->group_name,
                        'other_user_id' => $isGroupCall ? null : ($otherUser->id ?? 0),
                        'other_user_name' => $isGroupCall ? $log->group_name : ($otherUser->name ?? 'Unknown'),
                        'other_user_photo' => $isGroupCall ? null : ($otherUser->profile_photo ?? null),
                        'other_user_phone' => $isGroupCall ? null : ($otherUser->phone ?? null),
                        'other_user_email' => $isGroupCall ? null : ($otherUser->email ?? null),
                        'type' => $log->type,
                        'status' => $log->status,
                        'duration' => $log->duration,
                        'is_outgoing' => $isCaller,
                        'channel_name' => $log->channel_name,
                        'created_at' => $log->created_at->toIso8601String(),
                    ];

                    // For group calls, include member info
                    if ($isGroupCall && $log->group_id) {
                        try {
                            $members = GroupMember::where('group_id', $log->group_id)
                                ->join('users', 'users.id', '=', 'group_members.user_id')
                                ->select('users.id', 'users.name', 'users.profile_photo')
                                ->get()
                                ->map(function ($m) {
                                    return [
                                        'id' => $m->id,
                                        'name' => $m->name,
                                        'photo' => $m->profile_photo,
                                    ];
                                });
                            $result['group_members'] = $members;
                            $result['group_member_count'] = $members->count();
                        } catch (\Exception $e) {
                            $result['group_members'] = [];
                            $result['group_member_count'] = 0;
                        }
                    }

                    return $result;
                });

            return response()->json(['data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // GET /api/call-logs/group/{groupId}
    public function groupCallLogs(Request $request, $groupId)
    {
        try {
            $userId = $request->user()->id;

            // Verify user is a member of this group
            $isMember = GroupMember::where('group_id', $groupId)
                ->where('user_id', $userId)
                ->exists();

            if (!$isMember) {
                return response()->json(['error' => 'Not a member of this group'], 403);
            }

            $logs = CallLog::where('group_id', $groupId)
                ->with(['caller:id,name,profile_photo'])
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(function ($log) use ($userId) {
                    return [
                        'id' => $log->id,
                        'caller_id' => $log->caller_id,
                        'caller_name' => $log->caller->name ?? 'Unknown',
                        'caller_photo' => $log->caller->profile_photo ?? null,
                        'type' => $log->type,
                        'status' => $log->status,
                        'duration' => $log->duration,
                        'is_outgoing' => $log->caller_id == $userId,
                        'created_at' => $log->created_at->toIso8601String(),
                    ];
                });

            return response()->json(['data' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // POST /api/call-logs
    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'nullable|exists:users,id',
            'group_id' => 'nullable|string',
            'group_name' => 'nullable|string',
            'channel_name' => 'required|string',
            'type' => 'required|in:voice,video',
            'status' => 'required|in:missed,answered,declined',
            'duration' => 'nullable|integer|min:0',
        ]);

        $log = CallLog::create([
            'caller_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
            'group_id' => $request->group_id,
            'group_name' => $request->group_name,
            'channel_name' => $request->channel_name,
            'type' => $request->type,
            'status' => $request->status,
            'duration' => $request->duration ?? 0,
        ]);

        return response()->json(['data' => $log], 201);
    }
}
