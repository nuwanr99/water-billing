<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends outbound WhatsApp messages through the Hosthere Zender gateway:
 * a form POST to {base_url}/send/whatsapp authenticated by an API secret
 * and a connected-account id.
 */
class WhatsAppService
{
    /**
     * Whether gateway credentials are configured.
     */
    public function configured(): bool
    {
        return (string) config('services.hosthere_whatsapp.api_secret') !== ''
            && (string) config('services.hosthere_whatsapp.account') !== '';
    }

    /**
     * Send a plain text message. Returns true when the gateway accepted it.
     */
    public function sendText(string $recipient, string $message): bool
    {
        if (! $this->configured()) {
            report(new \RuntimeException('WhatsApp gateway is not configured; message not sent.'));

            return false;
        }

        $url = rtrim((string) config('services.hosthere_whatsapp.base_url'), '/').'/send/whatsapp';

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.hosthere_whatsapp.timeout', 15))
                ->post($url, [
                    'secret' => (string) config('services.hosthere_whatsapp.api_secret'),
                    'account' => (string) config('services.hosthere_whatsapp.account'),
                    'recipient' => $this->normalizeNumber($recipient),
                    'message' => $message,
                    'type' => 'text',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        $body = $response->json();
        $status = (int) data_get(is_array($body) ? $body : [], 'status', $response->status());

        return $response->successful() && $status >= 200 && $status < 300;
    }

    /**
     * Normalize a Sri Lankan phone number to international +94 format,
     * whether given as 0XXXXXXXXX, 7XXXXXXXX, or 94XXXXXXXXX.
     */
    public function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        return match (true) {
            str_starts_with($digits, '0') => '+94'.substr($digits, 1),
            str_starts_with($digits, '94') => '+'.$digits,
            default => '+94'.$digits,
        };
    }
}
