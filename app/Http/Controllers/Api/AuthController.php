<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\OtpVerification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // ── REQUEST OTP ───────────────────────────────────────────
    // Flutter kirim: name, phone, email, password
    // Laravel validasi → generate OTP → kirim email + WA → return success
    public function requestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|unique:users,phone',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Generate kode OTP 6 digit
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Simpan OTP + data user di Cache (berlaku 5 menit)
        $cacheKey = 'otp_' . $request->email;
        Cache::put($cacheKey, [
            'code'     => $otpCode,
            'name'     => $request->name,
            'phone'    => $request->phone,
            'email'    => $request->email,
            'password' => $request->password,
        ], now()->addMinutes(5));

        // Kirim OTP via Email
        try {
            Mail::to($request->email)->send(new OtpVerification($otpCode, 'register'));
            \Log::info('✅ Email OTP Register berhasil dikirim', ['email' => $request->email]);
        } catch (\Exception $e) {
            \Log::error('❌ Email OTP Register GAGAL', [
                'email'     => $request->email,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'code'      => $e->getCode(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        // Kirim OTP via WhatsApp
        try {
            $waService = new WhatsAppService();
            $waService->sendOtp($request->phone, $otpCode);
        } catch (\Exception $e) {
            \Log::warning('WhatsApp OTP gagal dikirim: ' . $e->getMessage());
        }

        \Log::info("🔑 OTP Register untuk {$request->email}: {$otpCode}");

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke email dan WhatsApp Anda',
            'expires_in' => 300,
        ]);
    }

    // ── VERIFY OTP ───────────────────────────────────────────
    // Flutter kirim: email, otp_code, fcm_token
    // Laravel cek OTP → buat user → return token
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Ambil data OTP dari cache
        $cacheKey = 'otp_' . $request->email;
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return response()->json([
                'message' => 'Kode OTP sudah kadaluarsa. Silakan minta kode baru.',
            ], 410);
        }

        if ($cached['code'] !== $request->otp_code) {
            return response()->json([
                'message' => 'Kode OTP salah. Pastikan Anda memasukkan kode yang benar.',
            ], 422);
        }

        // OTP valid → buat user
        // PENTING: Jangan pakai Hash::make() di sini!
        // Model User sudah punya cast 'password' => 'hashed' yang auto-hash.
        // Kalau pakai Hash::make() + cast = double hashing → login selalu 401.
        $user = User::create([
            'name'      => $cached['name'],
            'phone'     => $cached['phone'],
            'email'     => $cached['email'],
            'password'  => $cached['password'],
            'is_online' => true,
            'fcm_token' => $request->fcm_token,
        ]);

        // Hapus OTP dari cache
        Cache::forget($cacheKey);

        // Buat token Sanctum
        $token = $user->createToken('rupiachat')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'phone'     => $user->phone,
                'email'     => $user->email,
                'photo_url' => $user->profile_photo,
            ],
        ], 201);
    }

    // ── DAFTAR (Legacy — tetap dipertahankan) ─────────────────
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'phone'                 => 'required|string|unique:users,phone',
            'email'                 => 'required|email|unique:users,email',
            'password'              => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // PENTING: Jangan pakai Hash::make() di sini!
        // Model User sudah punya cast 'password' => 'hashed' yang auto-hash.
        // Kalau pakai Hash::make() + cast = double hashing → login selalu 401.
        $user = User::create([
            'name'      => $request->name,
            'phone'     => $request->phone,
            'email'     => $request->email,
            'password'  => $request->password,
            'is_online' => true,
            'fcm_token' => $request->fcm_token,
        ]);

        $token = $user->createToken('rupiachat')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'photo_url' => $user->profile_photo,
            ],
        ], 201);
    }

    // ── LOGIN ─────────────────────────────────────────────────
    public function login(Request $request)
    {
        // DEBUG: Log semua data request dari client (untuk diagnosa 401 di iPhone)
        \Log::info('🔐 LOGIN ATTEMPT', [
            'email'       => $request->email,
            'password_len'=> strlen($request->password ?? ''),
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'all_input'   => $request->except('password'),
            'content_type'=> $request->header('Content-Type'),
            'accept'      => $request->header('Accept'),
            'raw_body'    => substr($request->getContent(), 0, 500),
        ]);

        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            \Log::warning('🔐 LOGIN VALIDATION FAILED', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        \Log::info('🔐 LOGIN USER LOOKUP', [
            'email_searched' => $request->email,
            'user_found'     => $user ? true : false,
            'user_id'        => $user?->id,
            'password_hash'  => $user ? substr($user->password, 0, 20) . '...' : null,
            'hash_check'     => $user ? Hash::check($request->password, $user->password) : false,
        ]);

        if (!$user || !Hash::check($request->password, $user->password)) {
            \Log::warning('🔐 LOGIN FAILED - 401', [
                'reason' => !$user ? 'user_not_found' : 'wrong_password',
                'email'  => $request->email,
            ]);
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        $user->update([
            'is_online' => true,
            'fcm_token' => $request->fcm_token,
        ]);

        // Tidak menghapus semua token lama agar multi-device login tetap berfungsi
        $token = $user->createToken('rupiachat')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'photo_url' => $user->profile_photo,
            ],
        ]);
    }

    // ── LOGOUT ────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->update(['is_online' => false, 'fcm_token' => null]);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout',
        ]);
    }

    // ── FORGOT PASSWORD: REQUEST OTP ──────────────────────────
    public function forgotPasswordRequestOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'Email tidak terdaftar dalam sistem.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Generate kode OTP 6 digit
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Simpan OTP di Cache (berlaku 5 menit)
        $cacheKey = 'reset_otp_' . $request->email;
        Cache::put($cacheKey, [
            'code' => $otpCode,
        ], now()->addMinutes(5));

        // Kirim OTP via Email
        try {
            Mail::to($request->email)->send(new OtpVerification($otpCode, 'reset'));
            \Log::info('✅ Email Reset OTP berhasil dikirim', ['email' => $request->email]);
        } catch (\Exception $e) {
            \Log::error('❌ Email Reset OTP GAGAL', [
                'email'     => $request->email,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'code'      => $e->getCode(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        // Kirim OTP via WhatsApp (karena kita punya phone user)
        if ($user && $user->phone) {
            try {
                $waService = new WhatsAppService();
                $waService->sendOtp($user->phone, $otpCode);
            } catch (\Exception $e) {
                \Log::warning('WhatsApp Reset OTP gagal dikirim: ' . $e->getMessage());
            }
        }

        \Log::info("🔑 Reset OTP untuk {$request->email}: {$otpCode}");

        return response()->json([
            'message' => 'Kode sandi (OTP) pemulihan telah dikirim ke email dan WhatsApp Anda',
            'expires_in' => 300,
        ]);
    }

    // ── FORGOT PASSWORD: RESET PASSWORD ─────────────────────
    public function forgotPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email|exists:users,email',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|min:6', // password baru
        ], [
            'email.exists' => 'Email tidak terdaftar dalam sistem.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Ambil data OTP dari cache
        $cacheKey = 'reset_otp_' . $request->email;
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return response()->json([
                'message' => 'Kode OTP pemulihan sudah kadaluarsa. Silakan minta kode baru.',
            ], 410);
        }

        if ($cached['code'] !== $request->otp_code) {
            return response()->json([
                'message' => 'Kode OTP salah. Pastikan Anda memasukkan kode yang benar.',
            ], 422);
        }

        // OTP valid → ubah password
        $user = User::where('email', $request->email)->first();
        if ($user) {
            // PENTING: Jangan pakai Hash::make() di sini!
            // Model User sudah punya cast 'password' => 'hashed'
            // yang otomatis hash password saat di-set.
            // Kalau pakai Hash::make() + cast = double hashing → password selalu gagal.
            $user->password = $request->password;
            $user->save();
            
            // Putuskan semua sesi (opsional: agar login dari awal)
            $user->tokens()->delete();
        }

        // Hapus OTP dari cache
        Cache::forget($cacheKey);

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan masuk dengan password baru Anda.',
        ]);
    }
}
