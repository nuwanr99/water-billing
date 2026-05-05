<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use RuntimeException;

/**
 * Delivers one Sinhala maintenance-job notification to one recipient over
 * WhatsApp. One job per recipient so a single dead number never blocks the
 * rest; skipped silently without a number or gateway; a gateway rejection
 * throws so the job retries.
 */
#[Tries(3)]
#[Backoff([60, 300])]
class SendJobNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $recipient, public string $message) {}

    /**
     * Execute the job.
     */
    public function handle(WhatsAppService $whatsapp): void
    {
        if (! $whatsapp->configured()) {
            return;
        }

        $number = $this->recipient->wa_number ?? $this->recipient->phone;

        if (blank($number)) {
            return;
        }

        if (! $whatsapp->sendText($number, $this->message)) {
            throw new RuntimeException("WhatsApp gateway rejected job notification to user {$this->recipient->id}; will retry.");
        }
    }
}
