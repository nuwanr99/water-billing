<?php

use App\Enums\BillReminderType;
use App\Jobs\SendBillReminderViaWhatsApp;
use App\Jobs\SendBillViaWhatsApp;
use App\Models\BillReminder;
use App\Services\AccountLedgerService;
use App\Services\BillPdfService;
use App\Services\WhatsAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('the due reminder command queues reminders three days before the due date', function () {
    Bus::fake([SendBillReminderViaWhatsApp::class]);

    ['bill' => $bill] = billedAccount(10); // due in 15 days

    $this->travelTo($bill->due_date->copy()->subDays(3));

    $this->artisan('bills:send-due-reminders')
        ->expectsOutputToContain('Queued 1 due-date reminder(s).')
        ->assertSuccessful();

    Bus::assertDispatched(
        SendBillReminderViaWhatsApp::class,
        fn (SendBillReminderViaWhatsApp $job): bool => $job->bill->is($bill) && $job->type === BillReminderType::DueSoon,
    );
});

test('the overdue reminder command queues reminders on the due date and three days after', function () {
    Bus::fake([SendBillReminderViaWhatsApp::class]);

    ['bill' => $bill] = billedAccount(10);

    $this->travelTo($bill->due_date);
    $this->artisan('bills:send-overdue-reminders')->assertSuccessful();

    Bus::assertDispatched(
        SendBillReminderViaWhatsApp::class,
        fn (SendBillReminderViaWhatsApp $job): bool => $job->bill->is($bill) && $job->type === BillReminderType::DueDate,
    );

    $this->travelTo($bill->due_date->copy()->addDays(3));
    $this->artisan('bills:send-overdue-reminders')->assertSuccessful();

    Bus::assertDispatched(
        SendBillReminderViaWhatsApp::class,
        fn (SendBillReminderViaWhatsApp $job): bool => $job->bill->is($bill) && $job->type === BillReminderType::Overdue,
    );
});

test('the job sends the Sinhala reminder once and records it so reruns skip', function () {
    config()->set('services.hosthere_whatsapp.api_secret', 'test-secret');
    config()->set('services.hosthere_whatsapp.account', 'test-account');

    // Keep the generation-time delivery job off the sync queue — this test
    // counts gateway calls made by the reminder job alone.
    Bus::fake([SendBillViaWhatsApp::class]);
    Http::fake(['wa.hosthere.lk/*' => Http::response(['status' => 200, 'message' => 'queued'])]);

    ['bill' => $bill, 'account' => $account] = billedAccount(10); // total due 200.00
    $account->owner->update(['wa_number' => '0771234567']);

    $job = fn () => (new SendBillReminderViaWhatsApp($bill, BillReminderType::DueSoon))
        ->handle(app(WhatsAppService::class), app(AccountLedgerService::class), app(BillPdfService::class));

    $job();
    $job();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/send/whatsapp')
            && $request->hasFile('document_file')
            && str_contains($request->body(), '+94771234567')
            && str_contains($request->body(), 'හිග මුදල: රු. 200.00')
            && str_contains($request->body(), 'දිනට පෙර ගෙවිය යුතුය');
    });

    expect(BillReminder::query()->where('bill_id', $bill->id)->where('type', BillReminderType::DueSoon)->count())->toBe(1);
});
