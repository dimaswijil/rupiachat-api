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
            ['balance' => 100000]
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
            'order_id' => $orderId,
            'amount' => $request->amount,
            'type' => 'topup',
            'status' => 'pending',
        ]);

        // Xendit Secret Key configured in .env
        $secretKey = config('services.xendit.secret_key', '');

        $payload = [
            'external_id' => $orderId,
            'amount' => (int) $request->amount,
            'description' => 'Top Up Saldo RupiaChat',
            'success_redirect_url' => url('/api/payment/success'),
            'failure_redirect_url' => url('/api/payment/failed'),
        ];

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->post('https://api.xendit.co/v2/invoices', $payload);

            if ($response->successful()) {
                return response()->json([
                    'invoice_url' => $response->json('invoice_url'),
                ]);
            }

            Log::error('Xendit Invoice Error: ' . $response->body());
            return response()->json(['message' => 'Gagal menghubungi Xendit'], 500);

        } catch (\Exception $e) {
            Log::error('Xendit Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan sistem'], 500);
        }
    }

    // Xendit Webhook
    public function webhook(Request $request)
    {
        $webhookToken = config('services.xendit.webhook_token', '');
        
        // Verify Xendit Webhook Token
        $reqToken = $request->header('x-callback-token');
        if ($reqToken !== $webhookToken && !app()->environment('local')) {
            return response()->json(['message' => 'Invalid webhook token'], 403);
        }

        $notif = $request->all();
        $orderId = $notif['external_id'] ?? '';
        $status = $notif['status'] ?? '';

        $trx = WalletTransaction::where('order_id', $orderId)->first();

        if (!$trx) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($status === 'PAID' || $status === 'SETTLED') {
            if ($trx->status !== 'success') {
                $trx->status = 'success';
                // Update user wallet balance
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $trx->user_id],
                    ['balance' => 100000]
                );
                $wallet->increment('balance', $trx->amount);
            }
        } elseif ($status === 'EXPIRED') {
            $trx->status = 'failed';
        }

        $trx->save();

        return response()->json(['message' => 'OK']);
    }

    public function paymentSuccess()
    {
        return response(
            '<html>
                <body style="display:flex; justify-content:center; align-items:center; height:100vh; background-color:#E8F5E9; font-family:sans-serif; text-align:center;">
                    <div>
                        <h1 style="color:#2E7D32;">Pembayaran Berhasil!</h1>
                        <p>Saldo Anda akan segera ditambahkan.</p>
                        <p style="font-weight:bold; margin-top:20px;">Silakan tekan tombol <b>Selesai (Done)</b> atau tanda silang <b>(X)</b> di pojok atas untuk kembali ke aplikasi.</p>
                    </div>
                </body>
            </html>'
        )->header('Content-Type', 'text/html');
    }

    public function paymentFailed()
    {
        return response(
            '<html>
                <body style="display:flex; justify-content:center; align-items:center; height:100vh; background-color:#FFEBEE; font-family:sans-serif; text-align:center;">
                    <div>
                        <h1 style="color:#C62828;">Pembayaran Gagal!</h1>
                        <p>Transaksi Anda tidak dapat diproses saat ini.</p>
                        <p style="font-weight:bold; margin-top:20px;">Silakan tekan tombol <b>Selesai (Done)</b> atau tanda silang <b>(X)</b> di pojok atas untuk kembali ke aplikasi.</p>
                    </div>
                </body>
            </html>'
        )->header('Content-Type', 'text/html');
    }

}
