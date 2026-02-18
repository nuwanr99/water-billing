<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The bill is the customer-facing statement document (D-26): a frozen
     * snapshot generated on-site at reading confirmation (D-14). total_due
     * — not monthly_charge — is what the customer reads as "the bill".
     *
     * is_current is a nullable boolean on purpose (D-21): true on the live
     * bill, NULL once superseded by a reissue. NULLs never collide in the
     * unique index, so idempotency stays schema-enforced for the current
     * bill while superseded bills accumulate freely.
     */
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->foreignId('water_account_id')->constrained();
            $table->foreignId('meter_reading_id')->constrained();
            $table->char('billing_month', 7);
            $table->boolean('is_current')->nullable()->default(true);
            $table->decimal('usage_charge', 10, 2);
            $table->decimal('service_charge', 10, 2);
            $table->decimal('monthly_charge', 10, 2);
            $table->decimal('previous_balance', 12, 2);
            $table->decimal('total_due', 12, 2);
            $table->json('breakdown');
            $table->string('status');
            $table->date('due_date');
            $table->timestamp('approved_at');
            $table->foreignId('generated_by')->constrained('users');
            $table->foreignId('account_ledger_entry_id')->constrained('account_ledger_entries');
            $table->boolean('is_reissue')->default(false);
            $table->foreignId('supersedes_bill_id')->nullable()->constrained('bills');
            $table->timestamps();

            $table->unique(['water_account_id', 'billing_month', 'is_current']);
            $table->index('billing_month');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
