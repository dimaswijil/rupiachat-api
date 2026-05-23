<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpVerification extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $type; // 'register' atau 'reset'

    /**
     * Create a new message instance.
     */
    public function __construct(string $otpCode, string $type = 'register')
    {
        $this->otpCode = $otpCode;
        $this->type = $type;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->type === 'reset'
            ? '🔐 Kode Reset Password - RupiaChat'
            : '🔐 Kode Verifikasi OTP - RupiaChat';

        $title = $this->type === 'reset'
            ? 'Reset Password'
            : 'Verifikasi Akun';

        $description = $this->type === 'reset'
            ? 'Gunakan kode berikut untuk mereset password akun RupiaChat Anda:'
            : 'Gunakan kode berikut untuk memverifikasi akun RupiaChat Anda:';

        return $this->subject($subject)
                    ->html("
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='utf-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            <body style='margin:0; padding:0; background-color:#f0f4f8; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif;'>
                <table role='presentation' width='100%' cellspacing='0' cellpadding='0' style='background-color:#f0f4f8;'>
                    <tr>
                        <td align='center' style='padding: 40px 20px;'>
                            <table role='presentation' width='480' cellspacing='0' cellpadding='0' style='background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);'>
                                
                                <!-- Header Gradient -->
                                <tr>
                                    <td style='background: linear-gradient(135deg, #0D2B6B 0%, #1B72C0 50%, #2557B3 100%); padding: 32px 24px; text-align:center;'>
                                        <div style='width:56px; height:56px; background:rgba(255,255,255,0.2); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;'>
                                            <span style='color:white; font-size:22px; font-weight:bold;'>Rp</span>
                                        </div>
                                        <h1 style='color:white; font-size:22px; margin:0 0 4px 0; font-weight:700;'>RupiaChat</h1>
                                        <p style='color:rgba(255,255,255,0.8); font-size:13px; margin:0;'>{$title}</p>
                                    </td>
                                </tr>

                                <!-- Body -->
                                <tr>
                                    <td style='padding: 32px 32px 24px;'>
                                        <p style='color:#374151; font-size:14px; line-height:1.6; margin:0 0 8px 0;'>Halo! 👋</p>
                                        <p style='color:#6b7280; font-size:14px; line-height:1.6; margin:0 0 24px 0;'>{$description}</p>
                                        
                                        <!-- OTP Code -->
                                        <div style='text-align:center; margin: 24px 0;'>
                                            <div style='display:inline-block; background: linear-gradient(135deg, #EBF4FF 0%, #F0F7FF 100%); border: 2px solid #BFDBFE; border-radius:16px; padding: 20px 40px;'>
                                                <span style='font-size:36px; font-weight:800; letter-spacing:10px; color:#1B72C0; font-family: monospace;'>{$this->otpCode}</span>
                                            </div>
                                        </div>

                                        <!-- Timer Info -->
                                        <div style='text-align:center; background:#FEF3C7; border-radius:10px; padding:12px 16px; margin:20px 0;'>
                                            <span style='color:#92400E; font-size:13px;'>⏱ Kode ini berlaku selama <strong>5 menit</strong></span>
                                        </div>
                                        
                                        <!-- Warning -->
                                        <div style='background:#FEF2F2; border-left:4px solid #EF4444; border-radius:0 8px 8px 0; padding:12px 16px; margin:16px 0;'>
                                            <p style='color:#991B1B; font-size:12px; margin:0; line-height:1.5;'>
                                                🔒 <strong>Jangan bagikan kode ini kepada siapapun.</strong><br>
                                                Tim RupiaChat tidak akan pernah meminta kode OTP Anda.
                                            </p>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Footer -->
                                <tr>
                                    <td style='background:#f9fafb; padding:20px 32px; border-top:1px solid #e5e7eb;'>
                                        <p style='color:#9ca3af; font-size:11px; margin:0; text-align:center; line-height:1.5;'>
                                            Email ini dikirim otomatis oleh RupiaChat.<br>
                                            Jika Anda tidak meminta kode ini, abaikan email ini.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ");
    }
}
