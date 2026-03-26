<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Persists the member's selected water account across sessions and
     * devices (previously session-only). Accounts are single-owner (D-10),
     * so the users table is the natural home — there is no pivot.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('last_water_account_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('water_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_water_account_id');
        });
    }
};
