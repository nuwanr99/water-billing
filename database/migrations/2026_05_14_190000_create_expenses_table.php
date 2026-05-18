<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Society expenses (D-19, Phase 7). Each expense is a cash-basis outflow
     * that posts a balanced journal to the system ledger — debiting an
     * expense-category account and crediting the asset account it was paid
     * from. It may optionally be tied to a maintenance job, which traces the
     * spend back to the complaint that raised it.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number')->unique();
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->foreignId('category_account_id')->constrained('system_ledger_accounts');
            $table->foreignId('paid_from_account_id')->constrained('system_ledger_accounts');
            $table->foreignId('maintenance_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('system_ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('maintenance_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
