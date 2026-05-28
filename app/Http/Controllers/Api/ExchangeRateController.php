<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExchangeRateController extends Controller
{
    /**
     * GET /api/exchange-rate
     * 
     * Return kurs USD → IDR.
     * Cache di tabel settings, refresh jika data lebih dari 1 jam.
     * Sumber: Frankfurter API (European Central Bank, gratis tanpa API key).
     */
    public function index()
    {
        $cacheKey = 'exchange_rate_usd_idr';
        $setting = Setting::where('key', $cacheKey)->first();

        // Cek apakah cache masih valid (< 10 menit)
        if ($setting && $setting->updated_at->diffInMinutes(now()) < 10) {
            $cached = json_decode($setting->value, true);
            return response()->json([
                'usd_idr'    => $cached['rate'],
                'rate'       => $cached['rate'],
                'date'       => $cached['date'],
                'source'     => 'cache',
                'updated_at' => $setting->updated_at->toIso8601String(),
            ]);
        }

        // Fetch dari Frankfurter API
        try {
            $response = Http::timeout(10)
                ->get('https://api.frankfurter.dev/v1/latest', [
                    'from' => 'USD',
                    'to'   => 'IDR',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $rate = $data['rates']['IDR'] ?? null;
                $date = $data['date'] ?? now()->toDateString();

                if ($rate) {
                    // Simpan ke cache
                    Setting::setValue($cacheKey, json_encode([
                        'rate' => $rate,
                        'date' => $date,
                    ]));

                    return response()->json([
                        'usd_idr'    => $rate,
                        'rate'       => $rate,
                        'date'       => $date,
                        'source'     => 'frankfurter',
                        'updated_at' => now()->toIso8601String(),
                    ]);
                }
            }

            Log::warning('Frankfurter API response invalid: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Exchange Rate Fetch Error: ' . $e->getMessage());
        }

        // Fallback: return cache lama jika ada, atau default
        if ($setting) {
            $cached = json_decode($setting->value, true);
            return response()->json([
                'usd_idr'    => $cached['rate'],
                'rate'       => $cached['rate'],
                'date'       => $cached['date'],
                'source'     => 'cache_stale',
                'updated_at' => $setting->updated_at->toIso8601String(),
            ]);
        }

        // Tidak ada cache sama sekali — return default
        return response()->json([
            'usd_idr'    => 16500.00,
            'rate'       => 16500.00,
            'date'       => now()->toDateString(),
            'source'     => 'default',
            'updated_at' => now()->toIso8601String(),
        ]);
    }
}
