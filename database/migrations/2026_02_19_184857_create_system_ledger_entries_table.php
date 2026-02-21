<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Journal headers of the cash-basis system ledger (D-19): populated by
     * payments (Phase 4) and expenses (Phase 7) — never by bills or charges.
     * The posting service refuses to post an unbalanced journal.
     */
    public function up(): void
    {
        Schema::create('system_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->date('entry_date');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('description');
            $table->decimal('total_debit', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->boolean('is_balanced')->default(false);
            $table->boolean('is_posted')->default(false);
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('entry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_ledger_entries');
    }
};
