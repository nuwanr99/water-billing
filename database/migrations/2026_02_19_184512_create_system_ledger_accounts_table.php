<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Seeded chart of accounts for the society's double-entry system ledger
     * (D-16, D-19). Named system_ledger_accounts to avoid any collision with
     * water accounts. No management UI in this phase — rows come from the
     * seeder only.
     */
    public function up(): void
    {
        Schema::create('system_ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_ledger_accounts');
    }
};
