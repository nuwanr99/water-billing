<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('water_accounts', function (Blueprint $table) {
            $table->decimal('initial_reading', 10, 2)->unsigned()->default(0)->after('meter_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_accounts', function (Blueprint $table) {
            $table->dropColumn('initial_reading');
        });
    }
};
