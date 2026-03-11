<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payments are account-level (D-28): each one posts a negative entry to
     * the water account's ledger and a cash/income journal to the system
     * ledger — the two FKs record those postings. The schema is
     * gateway-ready (D-33): manual payments are born completed; the
     * pending/failed states and gateway columns serve the PayHere slice.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('water_account_id')->constrained();
            $table->foreignId('account_ledger_entry_id')->nullable()->unique()->constrained('account_ledger_entries');
            $table->foreignId('system_ledger_entry_id')->nullable()->constrained('system_ledger_entries');
            $table->foreignId('destination_account_id')->nullable()->constrained('system_ledger_accounts');
            $table->string('method');
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('payhere_reference')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->index('status');
            $table->index('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
