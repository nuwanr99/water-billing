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
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->char('billing_month', 7);
            $table->decimal('reading_value', 10, 2)->unsigned();
            $table->decimal('consumption', 10, 2)->unsigned();
            $table->date('reading_date');
            $table->timestamps();

            $table->unique(['water_account_id', 'billing_month']);
            $table->index('billing_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('meter_readings');
    }
};
