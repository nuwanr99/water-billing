<?php

use App\Models\WaterAccount;

test('the homepage shows the public pay form', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Welcome'));
});

test('the homepage form hands off to the payment page', function () {
    ['account' => $account] = billedAccount(10);

    /** @var WaterAccount $account */
    $this->post(route('pay.lookup'), [
        'account_number' => $account->account_number,
        'contact' => $account->owner->email,
    ])->assertRedirect(route('pay.show', [
        'account' => $account->account_number,
        'meter' => $account->meter_number,
    ]));
});
