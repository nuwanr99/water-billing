<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->member = User::factory()->create();
    $this->member->assignRole('Member');
});

test('a member sees only their own bills on the billing history', function () {
    $account = memberAccount($this->member);
    ['bill' => $bill] = billedAccount(10, $account); // 200.00

    $otherMember = User::factory()->create();
    $otherMember->assignRole('Member');
    billedAccount(5, memberAccount($otherMember));

    $this->actingAs($this->member)
        ->get(route('my.bills.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('my/bills/Index')
            ->count('bills.data', 1)
            ->where('bills.data.0.id', $bill->id)
            ->where('bills.data.0.total_due', 200)
        );
});

test('a member can view their own bill', function () {
    ['bill' => $bill] = billedAccount(10, memberAccount($this->member));

    $this->actingAs($this->member)
        ->get(route('bills.show', $bill))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('bills/Show'));
});

test('a member cannot view another member\'s bill', function () {
    $otherMember = User::factory()->create();
    $otherMember->assignRole('Member');

    ['bill' => $foreignBill] = billedAccount(10, memberAccount($otherMember));

    $this->actingAs($this->member)
        ->get(route('bills.show', $foreignBill))
        ->assertForbidden();
});

test('a member can download the PDF of their own bill', function () {
    ['bill' => $bill] = billedAccount(10, memberAccount($this->member));

    $this->actingAs($this->member)
        ->get(route('bills.pdf', $bill))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('a user with no roles cannot open the billing history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my.bills.index'))
        ->assertForbidden();
});

test('a water controller can still view any bill', function () {
    $waterController = User::factory()->create();
    $waterController->assignRole('Water Controller');

    ['bill' => $bill] = billedAccount(10, memberAccount($this->member));

    $this->actingAs($waterController)
        ->get(route('bills.show', $bill))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('bills/Show'));
});
