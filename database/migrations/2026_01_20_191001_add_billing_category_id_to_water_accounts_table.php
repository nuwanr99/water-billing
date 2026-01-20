<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nullable at the schema level so the column can be added alongside
     * pre-existing rows; the application requires a category on every
     * create and edit.
     */
    public function up(): void
    {
        Schema::table('water_accounts', function (Blueprint $table) {
            $table->foreignId('billing_category_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_category_id');
        });
    }
};
