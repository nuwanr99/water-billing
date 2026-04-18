<?php

use App\Jobs\SendReceiptViaWhatsApp;
use App\Models\SystemLedgerAccount;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReceiptPdfService;
use App\Services\WhatsAppService;
use Database\Seeders\SystemLedgerAccountSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
});

test('recording a manual payment queues WhatsApp delivery of the receipt', function () {
    Bus::fake([SendReceiptViaWhatsApp::class]);

    ['account' => $account] = billedAccount(10); // total due 200.00

    $payment = app(PaymentService::class)->record($account, 200.00, $this->cash, $this->treasurer);

    Bus::assertDispatched(SendReceiptViaWhatsApp::class, fn (SendReceiptViaWhatsApp $job): bool => $job->payment->is($payment));
});

test('completing a gateway payment queues WhatsApp delivery of the receipt', function () {
    Bus::fake([SendReceiptViaWhatsApp::class]);

    ['account' => $account] = billedAccount(10);

    $service = app(PaymentService::class);
    $intent = $service->createGatewayIntent($account, 200.00);
    $payment = $service->completeGateway($intent, ['payment_id' => 'PH-123']);

    Bus::assertDispatched(SendReceiptViaWhatsApp::class, fn (SendReceiptViaWhatsApp $job): bool => $job->payment->is($payment));
});

test('the job sends the receipt PDF as a WhatsApp document with the balance', function () {
    config()->set('services.hosthere_whatsapp.api_secret', 'test-secret');
    config()->set('services.hosthere_whatsapp.account', 'test-account');

    Bus::fake([SendReceiptViaWhatsApp::class]);
    Http::fake(['wa.hosthere.lk/*' => Http::response(['status' => 200, 'message' => 'WhatsApp chat has been sent!'])]);

    ['account' => $account] = billedAccount(10); // total due 200.00
    $account->owner->update(['wa_number' => '0771234567']);

    $payment = app(PaymentService::class)->record($account, 150.00, $this->cash, $this->treasurer);

    (new SendReceiptViaWhatsApp($payment))->handle(app(WhatsAppService::class), app(ReceiptPdfService::class));

    Http::assertSent(function (Request $request) use ($payment): bool {
        return str_contains($request->url(), '/send/whatsapp')
            && $request->hasFile('document_file')
            && str_contains($request->body(), '+94771234567')
            && str_contains($request->body(), 'ලදුපත් අංකය: '.$payment->receipt_number)
            && str_contains($request->body(), 'ඉතිරි හිග මුදල: රු. 50.00');
    });
});
