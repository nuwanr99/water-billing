<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Slabs are contiguous half-open ranges (lower_units, upper_units]: each
     * row's lower_units equals the previous row's upper_units, so fractional
     * consumption always lands in exactly one slab. A null upper_units marks
     * the open-ended top slab. service_charge is the monthly fixed charge
     * applied when the month's total consumption falls within the slab.
     */
    public function up(): void
    {
        Schema::create('tariff_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lower_units');
            $table->unsignedInteger('upper_units')->nullable();
            $table->decimal('rate_per_unit', 8, 2);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['billing_category_id', 'lower_units']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_tiers');
    }
};
