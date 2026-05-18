<?php

use App\Models\Expense;
use App\Models\MaintenanceJob;
use App\Models\SystemLedgerAccount;
use App\Models\SystemLedgerEntry;
use App\Models\User;
use App\Services\SystemLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemLedgerAccountSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SystemLedgerAccountSeeder::class);

    $this->treasurer = User::factory()->create();
    $this->treasurer->assignRole('Treasurer');

    $this->maintenance = SystemLedgerAccount::query()->where('code', '5000')->firstOrFail();
    $this->cash = SystemLedgerAccount::query()->where('code', '1000')->firstOrFail();
});

test('recording an expense posts a balanced journal and links it', function () {
    $this->actingAs($this->treasurer)
        ->post(route('admin.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'amount' => 1500.50,
            'category_account_id' => $this->maintenance->id,
            'paid_from_account_id' => $this->cash->id,
            'maintenance_job_id' => null,
            'description' => 'Replacement valve',
        ])
        ->assertRedirect();

    $expense = Expense::query()->firstOrFail();

    expect($expense->expense_number)->toStartWith('EXP-')
        ->and((float) $expense->amount)->toBe(1500.50)
        ->and($expense->system_ledger_entry_id)->not->toBeNull()
        ->and($expense->recorded_by)->toBe($this->treasurer->id);

    $entry = SystemLedgerEntry::query()->firstOrFail();

    expect($entry->source_type)->toBe('expense')
        ->and($entry->source_id)->toBe($expense->id)
        ->and($entry->is_balanced)->toBeTrue()
        ->and((float) $entry->total_debit)->toBe(1500.50);

    // Category is debited (+), cash is credited (−).
    expect((float) $this->maintenance->lines()->sum('amount'))->toBe(1500.50)
        ->and((float) $this->cash->lines()->sum('amount'))->toBe(-1500.50);
});

test('an expense can be linked to a maintenance job', function () {
    $job = MaintenanceJob::factory()->create();

    $this->actingAs($this->treasurer)
        ->post(route('admin.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'amount' => 800,
            'category_account_id' => $this->maintenance->id,
            'paid_from_account_id' => $this->cash->id,
            'maintenance_job_id' => $job->id,
            'description' => 'Pump repair parts',
        ])
        ->assertSessionHasNoErrors();

    expect(Expense::query()->firstOrFail()->maintenance_job_id)->toBe($job->id);
});

test('a reference image is stored and streamed back to a permitted viewer', function () {
    Storage::fake('local');

    $this->actingAs($this->treasurer)
        ->post(route('admin.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'amount' => 250,
            'category_account_id' => $this->maintenance->id,
            'paid_from_account_id' => $this->cash->id,
            'description' => 'Fittings with receipt',
            'reference_image' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $expense = Expense::query()->firstOrFail();
    $image = $expense->referenceImage;

    expect($image)->not->toBeNull()
        ->and($image->isImage())->toBeTrue();
    Storage::disk('local')->assertExists($image->path);

    $this->actingAs($this->treasurer)
        ->get(route('attachments.download', $image->id))
        ->assertOk();

    // A member cannot pull the private image.
    $member = User::factory()->create();
    $member->assignRole('Member');
    $this->actingAs($member)
        ->get(route('attachments.download', $image->id))
        ->assertForbidden();
});

test('a non-image reference file is rejected', function () {
    Storage::fake('local');

    $this->actingAs($this->treasurer)
        ->post(route('admin.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'amount' => 250,
            'category_account_id' => $this->maintenance->id,
            'paid_from_account_id' => $this->cash->id,
            'description' => 'Bad upload',
            'reference_image' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('reference_image');

    expect(Expense::query()->count())->toBe(0);
});

test('the create page offers expense categories and paid-from balances', function () {
    app(SystemLedgerService::class)->post('Opening cash', [
        ['account' => '1000', 'amount' => 5000.00],
        ['account' => '4100', 'amount' => -5000.00],
    ]);

    $this->actingAs($this->treasurer)
        ->get(route('admin.expenses.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/expenses/Create')
            ->where('categoryAccounts.0.code', '5000')
            ->where('paidFromAccounts.0.code', '1000')
            ->where('paidFromAccounts.0.balance', 5000)
        );
});

test('the category must be an expense account and paid-from an asset account', function () {
    // Asset account offered as a category, expense account offered as source.
    $this->actingAs($this->treasurer)
        ->post(route('admin.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'amount' => 100,
            'category_account_id' => $this->cash->id,
            'paid_from_account_id' => $this->maintenance->id,
            'description' => 'Miscategorised',
        ])
        ->assertSessionHasErrors(['category_account_id', 'paid_from_account_id']);

    expect(Expense::query()->count())->toBe(0);
});

test('an expense requires an amount and a description', function () {
    $this->actingAs($this->treasurer)
        ->post(route('admin.expenses.store'), [
            'expense_date' => now()->toDateString(),
            'category_account_id' => $this->maintenance->id,
            'paid_from_account_id' => $this->cash->id,
        ])
        ->assertSessionHasErrors(['amount', 'description']);
});

test('a user without expense permissions is refused', function () {
    $member = User::factory()->create();
    $member->assignRole('Member');

    $this->actingAs($member)->get(route('admin.expenses.index'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.expenses.create'))->assertForbidden();
    $this->actingAs($member)
        ->post(route('admin.expenses.store'), [])
        ->assertForbidden();
});
