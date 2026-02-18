<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cached balance per water account, updated in the same transaction as
     * every ledger entry. The row doubles as the per-account posting lock
     * (lockForUpdate), which serializes running-balance computation.
     * Recalculable from account_ledger_entries at any time.
     */
    public function up(): void
    {
        Schema::create('water_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_account_id')->unique()->constrained();
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('water_account_balances');
    }
};
