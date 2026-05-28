<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\UserFeature;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    /**
     * POST /api/purchases
     * Beli fitur — potong saldo, langsung aktif.
     */
    public function store(Request $request)
    {
        $request->validate([
            'feature_slug' => 'required|string|max:50',
            'feature_name' => 'required|string|max:100',
            'price'        => 'required|numeric|min:1000',
        ]);

        $user = $request->user();
        $featureSlug = $request->feature_slug;
        $price = $request->price;

        // Cek apakah user sudah punya fitur ini
        $existing = UserFeature::where('user_id', $user->id)
            ->where('feature_slug', $featureSlug)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Anda sudah memiliki fitur ini',
            ], 400);
        }

        // Cek saldo cukup
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 100000]
        );

        if ($wallet->balance < $price) {
            return response()->json([
                'message' => 'Saldo tidak mencukupi. Silakan Top Up terlebih dahulu.',
                'balance' => $wallet->balance,
            ], 400);
        }

        // Transaksi DB: potong saldo + buat purchase + aktifkan fitur
        try {
            DB::beginTransaction();

            // 1. Potong saldo
            $wallet->decrement('balance', $price);

            // 2. Catat pembelian
            $purchase = Purchase::create([
                'user_id'      => $user->id,
                'feature_slug' => $featureSlug,
                'feature_name' => $request->feature_name,
                'price'        => $price,
                'status'       => 'completed',
            ]);

            // 3. Aktifkan fitur
            UserFeature::create([
                'user_id'      => $user->id,
                'feature_slug' => $featureSlug,
                'purchase_id'  => $purchase->id,
                'activated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message'  => 'Berhasil membeli ' . $request->feature_name . '!',
                'balance'  => $wallet->refresh()->balance,
                'purchase' => $purchase,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses pembelian',
            ], 500);
        }
    }

    /**
     * GET /api/purchases/my
     * Riwayat pembelian user.
     */
    public function myPurchases(Request $request)
    {
        $purchases = Purchase::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'purchases' => $purchases,
        ]);
    }

    /**
     * GET /api/features/my
     * Daftar fitur yang sudah aktif untuk user.
     */
    public function myFeatures(Request $request)
    {
        $features = UserFeature::where('user_id', $request->user()->id)
            ->pluck('feature_slug')
            ->toArray();

        return response()->json([
            'features' => $features,
        ]);
    }
}
