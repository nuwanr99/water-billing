<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock movements (M7): the append-only history behind every item's
     * on-hand count. A purchase adds stock, a usage removes it (optionally
     * tied to the maintenance job that consumed it, D-45), and an adjustment
     * corrects it. `quantity` is signed at the application layer.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_job_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type');
            $table->integer('quantity');
            $table->decimal('unit_rate', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('moved_by')->constrained('users');
            $table->dateTime('moved_at');
            $table->timestamps();

            $table->index('inventory_item_id');
            $table->index('maintenance_job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
