<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The per-water-account statement (D-16, D-17): one signed entry per
     * financial event. Positive = the member owes more; negative = reduces
     * what they owe. Strictly append-only (D-25): rows are never updated or
     * deleted — corrections are reversing entries, and bill membership is
     * derived from cutoff position (an entry belongs to the bill whose
     * water_charge entry follows it), never stamped onto rows.
     */
    public function up(): void
    {
        Schema::create('account_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_account_id')->constrained();
            $table->string('entry_type');
            $table->decimal('amount', 12, 2);
            $table->decimal('running_balance', 12, 2);
            $table->date('entry_date');
            $table->char('billing_month', 7)->nullable();
            $table->string('document_number')->nullable();
            $table->string('description');
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['water_account_id', 'id']);
            $table->index('entry_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_ledger_entries');
    }
};
