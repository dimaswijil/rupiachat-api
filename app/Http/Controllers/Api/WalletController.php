<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['balance' => 0]
        );

        return response()->json([
            'balance' => $wallet->balance
        ]);
    }

    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
        ]);

        $user = $request->user();
        $orderId = 'TOPUP-' . $user->id . '-' . time();

        // Save pending transaction
        WalletTransaction::create([
            'user_id' => $user->id,
            'midtrans_order_id' => $orderId,
            'amount' => $request->amount,
            'type' => 'topup',
            'status' => 'pending',
        ]);

        // Midtrans Server Key (sandbox) configured in .env, falling back to empty
        $serverKey = env('MIDTRANS_SERVER_KEY', '');

        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $request->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ]
        ];

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->post('https://app.sandbox.midtrans.com/snap/v1/transactions', $payload);

            if ($response->successful()) {
                // Return snap token and redirect URL
                return response()->json([
                    'snap_token' => $response->json('token'),  // Digunakan oleh Flutter midtrans_sdk
                    'token' => $response->json('token'),        // Backward compatible
                    'redirect_url' => $response->json('redirect_url')
                ]);
            }

            Log::error('Midtrans Snap Error: ' . $response->body());
            return response()->json(['message' => 'Gagal menghubungi Midtrans'], 500);

        } catch (\Exception $e) {
            Log::error('Midtrans Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan sistem'], 500);
        }
    }

    // Midtrans Webhook (No CSRF needed, put in api route)
    public function webhook(Request $request)
    {
        $notif = $request->all();
        $serverKey = env('MIDTRANS_SERVER_KEY', '');

        // Verify signature
        $orderId = $notif['order_id'] ?? '';
        $statusCode = $notif['status_code'] ?? '';
        $grossAmount = $notif['gross_amount'] ?? '';
        $signatureKey = $notif['signature_key'] ?? '';

        // Generate our signature to compare (sha512(order_id + status_code + gross_amount + server_key))
        $mySignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($mySignature !== $signatureKey && env('APP_ENV') !== 'local') {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $trx = WalletTransaction::where('midtrans_order_id', $orderId)->first();
        if (!$trx) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transactionStatus = $notif['transaction_status'] ?? '';
        $fraudStatus = $notif['fraud_status'] ?? '';

        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            if ($fraudStatus == 'challenge') {
                $trx->status = 'pending';
            } else {
                if ($trx->status !== 'success') {
                    $trx->status = 'success';
                    // Update user wallet balance
                    $wallet = Wallet::firstOrCreate(['user_id' => $trx->user_id]);
                    $wallet->increment('balance', $trx->amount);
                }
            }
        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            $trx->status = 'failed';
        } else if ($transactionStatus == 'pending') {
            $trx->status = 'pending';
        }

        $trx->save();
        return response()->json(['message' => 'OK']);
    }

    public function history(Request $request)
    {
        $transactions = WalletTransaction::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($trx) {
                $data = $trx->toArray();
                // Sertakan nama user terkait untuk transaksi transfer
                // Agar Flutter bisa tampilkan "Dari Sari" atau "Ke Budi"
                if ($trx->reference_user_id) {
                    $refUser = \App\Models\User::find($trx->reference_user_id);
                    $data['reference_user_name'] = $refUser ? $refUser->name : 'Unknown';
                    $data['reference_user_photo'] = $refUser ? $refUser->profile_photo : null;
                }
                return $data;
            });

        return response()->json([
            'transactions' => $transactions
        ]);
    }

    public function transfer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'receiver_id' => 'required|exists:users,id'
        ]);

        $sender = $request->user();
        $amount = $request->amount;
        $receiverId = $request->receiver_id;

        if ($sender->id == $receiverId) {
            return response()->json(['message' => 'Tidak bisa transfer ke diri sendiri'], 400);
        }

        $senderWallet = Wallet::firstOrCreate(['user_id' => $sender->id], ['balance' => 0]);
        $receiverWallet = Wallet::firstOrCreate(['user_id' => $receiverId], ['balance' => 0]);

        if ($senderWallet->balance < $amount) {
            return response()->json(['message' => 'Saldo tidak mencukupi'], 400);
        }

        // Deduct sender balance
        $senderWallet->decrement('balance', $amount);
        
        // Add receiver balance
        $receiverWallet->increment('balance', $amount);

        // Record sender transaction (keluar)
        WalletTransaction::create([
            'user_id' => $sender->id,
            'midtrans_order_id' => 'TRF-OUT-' . time() . '-' . uniqid(),
            'amount' => $amount,
            'type' => 'transfer_out',
            'status' => 'success',
            'description' => 'Transfer keluar',
            'reference_user_id' => $receiverId,
        ]);

        // Record receiver transaction (masuk)
        WalletTransaction::create([
            'user_id' => $receiverId,
            'midtrans_order_id' => 'TRF-IN-' . time() . '-' . uniqid(),
            'amount' => $amount,
            'type' => 'transfer_in',
            'status' => 'success',
            'description' => 'Menerima transfer',
            'reference_user_id' => $sender->id,
        ]);

        return response()->json([
            'message' => 'Transfer berhasil',
            'balance' => $senderWallet->refresh()->balance
        ]);
    }
}
