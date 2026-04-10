<?php

use App\Jobs\SendBillViaWhatsApp;
use App\Models\BillingCategory;
use App\Models\MeterReading;
use App\Models\User;
use App\Models\WaterAccount;
use App\Services\BillGenerationService;
use App\Services\BillPdfService;
use App\Services\WhatsAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/**
 * An account whose owner has a WhatsApp number, with a billable reading
 * for the current month.
 *
 * @return array{account: WaterAccount, reading: MeterReading, user: User}
 */
function whatsAppBillableAccount(): array
{
    $category = BillingCategory::factory()->create();
    $category->tiers()->createMany([
        ['lower_units' => 0, 'upper_units' => 10, 'rate_per_unit' => 20.00, 'service_charge' => 0],
        ['lower_units' => 10, 'upper_units' => null, 'rate_per_unit' => 35.00, 'service_charge' => 0],
    ]);

    $owner = User::factory()->create(['wa_number' => '0771234567']);

    $account = WaterAccount::factory()->create([
        'user_id' => $owner->id,
        'billing_category_id' => $category->id,
        'initial_reading' => 1000,
    ]);

    $reading = MeterReading::factory()
        ->for($account)
        ->forMonth(now()->format('Y-m'))
        ->create(['reading_value' => 1012, 'consumption' => 12]);

    return ['account' => $account, 'reading' => $reading, 'user' => User::factory()->create()];
}

test('generating a bill queues WhatsApp delivery of the bill', function () {
    Bus::fake([SendBillViaWhatsApp::class]);

    ['reading' => $reading, 'user' => $user] = whatsAppBillableAccount();

    $bill = app(BillGenerationService::class)->generate($reading, $user);

    Bus::assertDispatched(SendBillViaWhatsApp::class, fn (SendBillViaWhatsApp $job): bool => $job->bill->is($bill));
});

test('the job sends the bill PDF as a WhatsApp document with a Sinhala summary', function () {
    config()->set('services.hosthere_whatsapp.api_secret', 'test-secret');
    config()->set('services.hosthere_whatsapp.account', 'test-account');

    Bus::fake([SendBillViaWhatsApp::class]);
    Http::fake(['wa.hosthere.lk/*' => Http::response(['status' => 200, 'message' => 'WhatsApp chat has been sent!'])]);

    ['reading' => $reading, 'user' => $user] = whatsAppBillableAccount();
    $bill = app(BillGenerationService::class)->generate($reading, $user);

    (new SendBillViaWhatsApp($bill))->handle(app(WhatsAppService::class), app(BillPdfService::class));

    Http::assertSent(function (Request $request) use ($bill): bool {
        return str_contains($request->url(), '/send/whatsapp')
            && $request->hasFile('document_file')
            && str_contains($request->body(), '+94771234567')
            && str_contains($request->body(), 'document')
            && str_contains($request->body(), 'බිල් අංකය: '.$bill->bill_number);
    });
});
