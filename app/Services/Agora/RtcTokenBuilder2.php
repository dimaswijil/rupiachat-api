<?php

namespace App\Services\Agora;

/**
 * RtcTokenBuilder2 — Builder token versi 007 untuk Agora RTC.
 *
 * Ini adalah wrapper yang mempermudah pembuatan token
 * menggunakan AccessToken2 (v007).
 *
 * Diambil dari repository resmi Agora:
 * https://github.com/AgoraIO/Tools/tree/master/DynamicKey/AgoraDynamicKey/php/src
 */
class RtcTokenBuilder2
{
    /**
     * Gunakan role ini untuk voice/video call biasa
     */
    const ROLE_PUBLISHER = 1;

    /**
     * Gunakan role ini jika mengaktifkan co-host authentication
     */
    const ROLE_SUBSCRIBER = 2;

    /**
     * Build token dengan UID (integer).
     * Secara internal, memanggil buildTokenWithUserAccount
     * karena AccessToken2 memperlakukan semua uid sebagai string.
     */
    public static function buildTokenWithUid($appId, $appCertificate, $channelName, $uid, $role, $tokenExpire, $privilegeExpire = 0)
    {
        return self::buildTokenWithUserAccount($appId, $appCertificate, $channelName, (string) $uid, $role, $tokenExpire, $privilegeExpire);
    }

    /**
     * Build token dengan User Account (string).
     *
     * @param string $appId          - App ID dari Agora Console
     * @param string $appCertificate - App Certificate dari Agora Console
     * @param string $channelName    - Nama channel (misal: "call_7_9")
     * @param string $account        - User account (misal: "7")
     * @param int    $role           - ROLE_PUBLISHER atau ROLE_SUBSCRIBER
     * @param int    $tokenExpire    - Berapa detik token valid (misal: 3600 = 1 jam)
     * @param int    $privilegeExpire - Berapa detik privilege valid (0 = sama dengan tokenExpire)
     * @return string Token yang siap dipakai oleh Agora SDK
     */
    public static function buildTokenWithUserAccount($appId, $appCertificate, $channelName, $account, $role, $tokenExpire, $privilegeExpire = 0)
    {
        $token = new AccessToken2($appId, $appCertificate, $tokenExpire);
        $serviceRtc = new ServiceRtc($channelName, $account);

        // FIXED: Convert privilegeExpire from duration (seconds) to Unix timestamp.
        // addPrivilege() expects Unix timestamp, not seconds.
        // Passing 3600 raw → Agora reads as Jan 1 1970 01:00 → instant expiry.
        // 0 means "no independent privilege expiry" (follows token expiry).
        $privilegeExpireTs = $privilegeExpire > 0 ? time() + $privilegeExpire : 0;

        $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_JOIN_CHANNEL, $privilegeExpireTs);
        if ($role == self::ROLE_PUBLISHER) {
            $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_AUDIO_STREAM, $privilegeExpireTs);
            $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_VIDEO_STREAM, $privilegeExpireTs);
            $serviceRtc->addPrivilege(ServiceRtc::PRIVILEGE_PUBLISH_DATA_STREAM, $privilegeExpireTs);
        }
        $token->addService($serviceRtc);

        return $token->build();
    }
}
