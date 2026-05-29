<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Service — Kirim OTP via WhatsApp Cloud API (Meta).
 * 
 * Docs: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class WhatsAppService
{
    private string $token;
    private string $phoneNumberId;
    private string $apiVersion = 'v21.0';

    public function __construct()
    {
        $this->token = config('services.whatsapp.token', '');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id', '');
    }

    /**
     * Kirim OTP ke nomor WhatsApp via Meta Cloud API.
     *
     * @param string $phone  Nomor telepon (format: 08xxx atau 628xxx)
     * @param string $code   Kode OTP 6 digit
     * @return bool
     */
    public function sendOtp(string $phone, string $code): bool
    {
        $phone = $this->normalizePhone($phone);

        // Cek apakah credentials sudah diisi
        if (empty($this->token) || empty($this->phoneNumberId) 
            || $this->token === 'ISI_TOKEN_DISINI'
            || $this->phoneNumberId === 'ISI_PHONE_NUMBER_ID_DISINI') {
            Log::warning('📱 WhatsApp API belum dikonfigurasi. OTP hanya di-log.', [
                'phone' => $phone,
                'code'  => $code,
            ]);
            return false;
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        try {
            $response = Http::timeout(5)
                ->withToken($this->token)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => 'rupiachat_otp', // Nama template yang kita buat di Meta
                        'language' => [
                            'code' => 'id' // Bahasa Indonesia
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $code // Variabel {{1}} berisi kode OTP
                                    ]
                                ]
                            ],
                            [
                                'type' => 'button',
                                'sub_type' => 'url',
                                'index' => '0',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $code // Variabel untuk tombol "Copy Code"
                                    ]
                                ]
                            ]
                        ]
                    ],
                ]);

            $result = $response->json();

            if ($response->successful() && isset($result['messages'])) {
                Log::info('📱 WhatsApp OTP berhasil dikirim', [
                    'phone'      => $phone,
                    'message_id' => $result['messages'][0]['id'] ?? null,
                ]);
                return true;
            }

            Log::error('📱 WhatsApp OTP gagal', [
                'phone'    => $phone,
                'response' => $result,
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('📱 WhatsApp OTP exception: ' . $e->getMessage(), [
                'phone' => $phone,
            ]);
            return false;
        }
    }

    /**
     * Normalisasi nomor telepon ke format internasional.
     * 08xxx → 628xxx
     * +628xxx → 628xxx
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        return $phone;
    }
}
