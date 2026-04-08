<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.hosthere_whatsapp.api_secret', 'test-secret');
    config()->set('services.hosthere_whatsapp.account', 'test-account');
});

function fakeWhatsAppGateway(): void
{
    Http::fake([
        'wa.hosthere.lk/*' => Http::response(['status' => 200, 'message' => 'queued'], 200),
    ]);
}

function sentOtpCode(): string
{
    $code = null;

    Http::assertSent(function ($request) use (&$code) {
        preg_match('/\d{6}/', (string) $request['message'], $matches);
        $code = $matches[0] ?? null;

        return $request['type'] === 'text';
    });

    return (string) $code;
}

test('users can request a login code over whatsapp', function () {
    fakeWhatsAppGateway();

    $user = User::factory()->create(['phone' => '0771234567']);

    $response = $this->from(route('login'))->post(route('login.otp.request'), [
        'phone' => '0771234567',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    Http::assertSent(fn ($request) => $request['recipient'] === '+94771234567');
});

test('users can log in with a valid code', function () {
    fakeWhatsAppGateway();

    $user = User::factory()->create(['phone' => '0771234567']);

    $this->post(route('login.otp.request'), ['phone' => '0771234567']);

    $response = $this->post(route('login.otp.verify'), [
        'phone' => '0771234567',
        'code' => sentOtpCode(),
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('an invalid code is rejected', function () {
    fakeWhatsAppGateway();

    User::factory()->create(['phone' => '0771234567']);

    $this->post(route('login.otp.request'), ['phone' => '0771234567']);

    $response = $this->post(route('login.otp.verify'), [
        'phone' => '0771234567',
        'code' => '000000',
    ]);

    $response->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('a code can be requested with any sri lankan number format', function (string $phone) {
    fakeWhatsAppGateway();

    User::factory()->create(['phone' => '0771234567']);

    $response = $this->post(route('login.otp.request'), ['phone' => $phone]);

    $response->assertSessionHasNoErrors();
    Http::assertSent(fn ($request) => $request['recipient'] === '+94771234567');
})->with(['+94771234567', '0771234567', '771234567']);

test('non sri lankan numbers are rejected', function (string $phone) {
    Http::fake();

    $response = $this->post(route('login.otp.request'), ['phone' => $phone]);

    $response->assertSessionHasErrors('phone');
    Http::assertNothingSent();
})->with(['+18005551234', '12345', '0771', '871234567']);
