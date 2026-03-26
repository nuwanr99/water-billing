<?php

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\AccountLedgerEntry;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountLedgerService;
use App\Services\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    config([
        'payhere.merchant_id' => '1211149',
        'payhere.merchant_secret' => 'test-secret',
        'payhere.sandbox' => true,
    ]);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');
});

/**
 * A verified-looking PayHere notify payload for the given intent.
 *
 * @return array<string, string>
 */
function payHerePayload(Payment $payment, int $statusCode = 2, ?string $amount = null): array
{
    $amount ??= number_format((float) $payment->amount, 2, '.', '');
    $orderId = 'PMT-'.$payment->id;

    return [
        'merchant_id' => '1211149',
        'order_id' => $orderId,
        'payment_id' => '320025',
        'payhere_amount' => $amount,
        'payhere_currency' => 'LKR',
        'status_code' => (string) $statusCode,
        'md5sig' => strtoupper(md5(
            '1211149'.$orderId.$amount.'LKR'.$statusCode.strtoupper(md5('test-secret'))
        )),
    ];
}

test('the QR parameters open the payment page with the balance', function () {
    ['account' => $account] = billedAccount(10); // 200.00 due

    $this->get(route('pay.show', ['account' => $account->account_number, 'meter' => $account->meter_number]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pay/Pay')
            ->where('account.balance', 200)
            ->where('account.owner_name', $account->owner->name)
        );
});

test('wrong or missing parameters fall back to the lookup form', function () {
    ['account' => $account] = billedAccount(10);

    $this->get(route('pay.show'))
        ->assertInertia(fn ($page) => $page->component('pay/Lookup')->where('notFound', false));

    $this->get(route('pay.show', ['account' => $account->account_number, 'meter' => 'MTR-WRONG']))
        ->assertInertia(fn ($page) => $page->component('pay/Lookup')->where('notFound', true));
});

test('manual lookup validates the account number with a phone in any format', function () {
    ['account' => $account] = billedAccount(10);
    $account->owner->update(['phone' => '0771234567']);

    $this->post(route('pay.lookup'), [
        'account_number' => $account->account_number,
        'contact' => '+94 77 123 4567',
    ])->assertRedirect(route('pay.show', [
        'account' => $account->account_number,
        'meter' => $account->meter_number,
    ]));

    $this->post(route('pay.lookup'), [
        'account_number' => $account->account_number,
        'contact' => '0779999999',
    ])->assertSessionHasErrors('contact');
});

test('manual lookup accepts the account number without its prefix', function () {
    ['account' => $account] = billedAccount(10);
    $account->owner->update(['phone' => '0771234567']);

    // "ACC-8572" → users type just "8572".
    $bareNumber = Str::afterLast($account->account_number, '-');

    $this->post(route('pay.lookup'), [
        'account_number' => $bareNumber,
        'contact' => '0771234567',
    ])->assertRedirect(route('pay.show', [
        'account' => $account->account_number,
        'meter' => $account->meter_number,
    ]));

    // Lowercase full number works too.
    $this->post(route('pay.lookup'), [
        'account_number' => strtolower($account->account_number),
        'contact' => '0771234567',
    ])->assertRedirect();
});

test('manual lookup accepts the account number without leading zeros', function () {
    ['account' => $account] = billedAccount(10);
    $account->update(['account_number' => 'ACC-001']);
    $account->owner->update(['phone' => '0771234567']);

    // "ACC-001" → customers type just "1" (or "01", or "001").
    foreach (['1', '01', '001'] as $typed) {
        $this->post(route('pay.lookup'), [
            'account_number' => $typed,
            'contact' => '0771234567',
        ])->assertRedirect(route('pay.show', [
            'account' => 'ACC-001',
            'meter' => $account->meter_number,
        ]));
    }
});

test('checkout creates a pending intent with no receipt number or ledger postings', function () {
    ['account' => $account] = billedAccount(10);

    $this->post(route('pay.checkout', ['account' => $account->account_number, 'meter' => $account->meter_number]), [
        'amount' => 200.00,
    ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pay/Checkout')
            ->where('fields.order_id', 'PMT-1')
            ->where('fields.amount', '200.00')
            ->has('fields.hash')
        );

    $payment = Payment::firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->receipt_number)->toBeNull()
        ->and($payment->account_ledger_entry_id)->toBeNull();

    // Balance untouched until the webhook confirms.
    expect(app(AccountLedgerService::class)->balanceFor($account))->toBe(200.00);
});

test('a verified success webhook completes the payment and settles the bill', function () {
    ['account' => $account, 'bill' => $bill] = billedAccount(10); // 200.00

    $payment = app(PaymentService::class)->createGatewayIntent($account, 200.00);

    $this->post(route('payhere.notify'), payHerePayload($payment))->assertOk();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Completed)
        ->and($payment->receipt_number)->toStartWith('RCPT-')
        ->and($payment->payhere_reference)->toBe('320025')
        ->and($payment->destinationAccount->code)->toBe('1100');

    expect($bill->refresh()->status)->toBe(BillStatus::Paid)
        ->and(app(AccountLedgerService::class)->balanceFor($account))->toBe(0.00);

    // Duplicate delivery posts nothing twice — but is kept in the history.
    $this->post(route('payhere.notify'), payHerePayload($payment))->assertOk();

    expect(AccountLedgerEntry::query()->where('entry_type', 'payment')->count())->toBe(1);

    $attempts = $payment->refresh()->gateway_payload;

    expect($attempts)->toHaveCount(2)
        ->and($attempts[0]['success'])->toBeTrue()
        ->and($attempts[0]['message'])->toBe('Payment completed.')
        ->and($attempts[1]['message'])->toBe('Duplicate notification — payment already completed.')
        ->and($attempts[0]['payload']['payment_id'])->toBe('320025');
});

test('a webhook with a manipulated amount is rejected even when internally signed', function () {
    ['account' => $account] = billedAccount(10);

    $payment = app(PaymentService::class)->createGatewayIntent($account, 200.00);

    // Amount inflated to 999.00 with a signature recomputed over the
    // tampered value — internally consistent, but it cannot match the
    // expectation built from our own record.
    $tampered = payHerePayload($payment, amount: '999.00');

    $this->post(route('payhere.notify'), $tampered)->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(AccountLedgerEntry::query()->where('entry_type', 'payment')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'payment.webhook-rejected')->exists())->toBeTrue();

    // The tampering attempt itself is kept as a trace on the payment,
    // logged before verification ran.
    expect($payment->gateway_payload)->toHaveCount(1)
        ->and($payment->gateway_payload[0]['success'])->toBeFalse()
        ->and($payment->gateway_payload[0]['message'])->toBe('Signature mismatch against local record.')
        ->and($payment->gateway_payload[0]['payload']['payhere_amount'])->toBe('999.00');
});

test('webhook payloads are sanitized before being stored', function () {
    ['account' => $account] = billedAccount(10);

    $payment = app(PaymentService::class)->createGatewayIntent($account, 200.00);

    $payload = payHerePayload($payment);
    $payload['evil_extra'] = str_repeat('x', 5000);
    $payload['nested'] = ['not' => 'scalar'];
    $payload['status_message'] = str_repeat('y', 5000);

    $this->post(route('payhere.notify'), $payload)->assertOk();

    $stored = $payment->refresh()->gateway_payload[0]['payload'];

    expect($stored)->not->toHaveKeys(['evil_extra', 'nested'])
        ->and(mb_strlen($stored['status_message']))->toBe(200)
        ->and($payment->status)->toBe(PaymentStatus::Completed);
});

test('a webhook with a bad signature is rejected and posts nothing', function () {
    ['account' => $account] = billedAccount(10);

    $payment = app(PaymentService::class)->createGatewayIntent($account, 200.00);

    $payload = payHerePayload($payment);
    $payload['md5sig'] = 'FORGED';

    $this->post(route('payhere.notify'), $payload)->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and(AuditLog::query()->where('action', 'payment.webhook-rejected')->exists())->toBeTrue();
});

test('a canceled webhook marks the intent failed', function () {
    ['account' => $account] = billedAccount(10);

    $payment = app(PaymentService::class)->createGatewayIntent($account, 200.00);

    $this->post(route('payhere.notify'), payHerePayload($payment, statusCode: -1))->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);

    // Failures leave a trace too.
    expect($payment->gateway_payload)->toHaveCount(1)
        ->and($payment->gateway_payload[0]['success'])->toBeFalse()
        ->and($payment->gateway_payload[0]['payload']['status_code'])->toBe('-1');
});

test('the result page reports status and offers the PDF receipt when completed', function () {
    ['account' => $account] = billedAccount(10);

    $payment = app(PaymentService::class)->createGatewayIntent($account, 200.00);

    $this->get(route('pay.result', $payment->public_token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('pay/Result')
            ->where('payment.status', 'pending')
            ->where('payment.receipt_pdf_url', null)
        );

    $this->post(route('payhere.notify'), payHerePayload($payment));

    $this->get(route('pay.result', $payment->public_token))
        ->assertInertia(fn ($page) => $page
            ->where('payment.status', 'completed')
            ->where('account.balance', 0)
        );

    $this->get(route('pay.receipt-pdf', $payment->public_token))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a member can only start payments for their own accounts', function () {
    ['account' => $account] = billedAccount(10);

    $stranger = User::factory()->create();
    $stranger->assignRole('Member');

    $this->actingAs($stranger)->get(route('my.pay.show', $account))->assertForbidden();
    $this->actingAs($stranger)->post(route('my.pay.checkout', $account), ['amount' => 100])->assertForbidden();

    $this->actingAs($account->owner)
        ->get(route('my.pay.show', $account))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('pay/Pay')->where('account.balance', 200));
});
