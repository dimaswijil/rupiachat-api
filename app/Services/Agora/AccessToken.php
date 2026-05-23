<?php

namespace App\Services\Agora;

/**
 * Agora AccessToken Generator (Version 006)
 *
 * Mengimplementasikan algoritma resmi Agora untuk generate token.
 * Token ini dipakai oleh Agora RTC SDK di client (Flutter) untuk
 * join ke channel voice/video call.
 *
 * Algoritma:
 * 1. Buat "message" berisi salt (random), timestamp (expire), dan privileges
 * 2. Sign message dengan HMAC-SHA256 menggunakan App Certificate
 * 3. Gabungkan signature + CRC32(channelName) + CRC32(uid) + message
 * 4. Encode ke base64, prefix dengan version + appId
 */
class AccessToken
{
    // ── Privilege Constants ──────────────────────────────────────
    // Setiap privilege menentukan apa yang bisa dilakukan user di channel
    const PRIVILEGE_JOIN_CHANNEL = 1;        // Boleh join channel
    const PRIVILEGE_PUBLISH_AUDIO = 2;       // Boleh kirim audio
    const PRIVILEGE_PUBLISH_VIDEO = 3;       // Boleh kirim video
    const PRIVILEGE_PUBLISH_DATA = 4;        // Boleh kirim data stream

    // ── Properties ───────────────────────────────────────────────
    public string $appId;
    public string $appCertificate;
    public string $channelName;
    public string $uid;
    public int $salt;          // Random number untuk keamanan
    public int $ts;            // Timestamp token dibuat
    public array $privileges;  // Map privilege_type => expire_timestamp

    /**
     * @param string $appId          - Agora App ID dari console.agora.io
     * @param string $appCertificate - Agora App Certificate (secret key)
     * @param string $channelName    - Nama channel (biasanya format: "call_userId1_userId2")
     * @param string $uid            - User ID (string) yang akan join channel
     */
    public function __construct(string $appId, string $appCertificate, string $channelName, string $uid)
    {
        $this->appId = $appId;
        $this->appCertificate = $appCertificate;
        $this->channelName = $channelName;
        $this->uid = $uid;
        $this->salt = random_int(1, 99999999);   // Random salt untuk setiap token
        $this->ts = time() + 24 * 3600;          // Default expire: 24 jam dari sekarang
        $this->privileges = [];
    }

    /**
     * Tambahkan privilege ke token.
     * Privilege menentukan apa yang bisa dilakukan user di channel.
     *
     * @param int $privilege      - Salah satu PRIVILEGE_* constant
     * @param int $expireTimestamp - Kapan privilege ini expire (unix timestamp)
     */
    public function addPrivilege(int $privilege, int $expireTimestamp): void
    {
        $this->privileges[$privilege] = $expireTimestamp;
    }

    /**
     * Build final token string.
     *
     * Format output: "006" + appId + base64(signature + crc_channel + crc_uid + message)
     *
     * @return string Token yang siap dipakai oleh Agora SDK
     */
    public function build(): string
    {
        // Step 1: Pack message (salt + ts + privileges) ke binary
        $message = $this->packMessage();

        // Step 2: Buat signature = HMAC-SHA256(certificate, appId + channel + uid + message)
        $toSign = $this->appId . $this->channelName . $this->uid . $message;
        $signature = hash_hmac('sha256', $toSign, $this->appCertificate, true);

        // Step 3: Gabungkan semua komponen ke binary content
        $content = '';
        $content .= $this->packString($signature);                           // Signature
        $content .= pack('V', crc32($this->channelName) & 0xFFFFFFFF);     // CRC32 channel
        $content .= pack('V', crc32($this->uid) & 0xFFFFFFFF);             // CRC32 uid
        $content .= $this->packString($message);                            // Message

        // Step 4: Prefix dengan version "006" (yang terbaru baru pake 007 brow ) + appId, lalu base64 encode content
        return '006' . $this->appId . base64_encode($content);
    }

    /**
     * Pack message: berisi salt, timestamp, dan map of privileges.
     * Format binary: salt(uint32) + ts(uint32) + privilege_count(uint16) + [privilege_type(uint16) + expire(uint32)]...
     */
    private function packMessage(): string
    {
        $msg = '';
        $msg .= pack('V', $this->salt);     // Salt: 4 bytes little-endian
        $msg .= pack('V', $this->ts);       // Timestamp: 4 bytes little-endian

        // Pack privileges map
        $msg .= pack('v', count($this->privileges));  // Count: 2 bytes little-endian
        foreach ($this->privileges as $key => $value) {
            $msg .= pack('v', $key);    // Privilege type: 2 bytes
            $msg .= pack('V', $value);  // Expire timestamp: 4 bytes
        }

        return $msg;
    }

    /**
     * Pack string dengan length prefix (2 bytes little-endian).
     * Format: length(uint16) + string_bytes
     */
    private function packString(string $value): string
    {
        return pack('v', strlen($value)) . $value;
    }
}
