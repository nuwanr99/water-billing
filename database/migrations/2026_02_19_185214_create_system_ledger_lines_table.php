<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Journal lines: signed amounts against the chart of accounts — positive
     * = debit, negative = credit. A journal's lines must sum to zero.
     */
    public function up(): void
    {
        Schema::create('system_ledger_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_ledger_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('system_ledger_account_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->unsignedInteger('line_number');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['system_ledger_entry_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_ledger_lines');
    }
};
