<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\GroupMessageController;
use Illuminate\Support\Facades\Route;

// ── Public routes (tidak perlu token) ─────────────────────
Route::get("/ping", function () { return response()->json(["status" => "ok"]); });
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/midtrans/webhook', [WalletController::class, 'webhook']);

// ── OTP Verification ──────────────────────────────────────
Route::post('/auth/request-otp', [AuthController::class, 'requestOtp']);
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPasswordRequestOtp']);
Route::post('/auth/reset-password', [AuthController::class, 'forgotPasswordReset']);

// ── Protected routes (wajib pakai Bearer token) ───────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // ── Call Logs ──────────────────────────────────────────
    Route::get('/call-logs', [\App\Http\Controllers\CallLogController::class, 'index']);
    Route::post('/call-logs', [\App\Http\Controllers\CallLogController::class, 'store']);
    Route::get('/call-logs/group/{groupId}', [\App\Http\Controllers\CallLogController::class, 'groupCallLogs']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/search', [UserController::class, 'search']);

    // FIX: mark-as-read dipindah ke ATAS {roomId} biar gak keswipe sebagai roomId
    Route::post('/messages/mark-as-read', [MessageController::class, 'markAsRead']);
    Route::post('/messages/typing', [MessageController::class, 'typing']);

    Route::get('/messages/{roomId}', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);

    Route::post('/user/update-photo', [UserController::class, 'updatePhoto']);
    Route::post('/user/update-profile', [UserController::class, 'updateProfile']);

    Route::post('/rooms/archive', [MessageController::class, 'archive']);
    Route::post('/rooms/pin', [MessageController::class, 'pin']);
    Route::post('/rooms/delete', [MessageController::class, 'deleteRoom']);

    // Wallet (hanya saldo + top up, transfer dihapus)
    Route::get('/wallet', [WalletController::class, 'index']);
    Route::post('/wallet/topup', [WalletController::class, 'topup']);

    // Purchase (beli fitur)
    Route::post('/purchases', [PurchaseController::class, 'store']);
    Route::get('/purchases/my', [PurchaseController::class, 'myPurchases']);
    Route::get('/features/my', [PurchaseController::class, 'myFeatures']);

    // Exchange Rate (kurs USD/IDR)
    Route::get('/exchange-rate', [ExchangeRateController::class, 'index']);

    // FCM token update
    Route::post('/user/fcm-token', function (\Illuminate\Http\Request $request) {
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['status' => 'success']);
    });

    // ── Groups ────────────────────────────────────────────
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::get('/groups/{id}', [GroupController::class, 'show']);
    Route::put('/groups/{id}', [GroupController::class, 'update']);
    Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
    Route::post('/groups/{id}/photo', [GroupController::class, 'updatePhoto']);
    Route::post('/groups/{id}/members', [GroupController::class, 'addMembers']);
    Route::delete('/groups/{id}/members/{userId}', [GroupController::class, 'removeMember']);
    Route::post('/groups/{id}/members/{userId}/make-admin', [GroupController::class, 'makeAdmin']);
    Route::post('/groups/{id}/members/{userId}/remove-admin', [GroupController::class, 'removeAdmin']);
    Route::post('/groups/{id}/leave', [GroupController::class, 'leave']);
    Route::post('/groups/{id}/pin', [GroupController::class, 'pinGroup']);

    // Group Messages
    Route::get('/groups/{id}/messages', [GroupMessageController::class, 'index']);
    Route::post('/groups/{id}/messages', [GroupMessageController::class, 'store']);

    // ── Agora Calls & Signaling ──────────────────────────────
    Route::post('/agora/token', [\App\Http\Controllers\Api\AgoraController::class, 'generateToken']);
    Route::post('/agora/call', [\App\Http\Controllers\Api\AgoraController::class, 'initiateCall']);
    Route::post('/agora/group-call', [\App\Http\Controllers\Api\AgoraController::class, 'initiateGroupCall']);
    Route::post('/agora/signal', [\App\Http\Controllers\Api\AgoraController::class, 'sendSignal']);
});
