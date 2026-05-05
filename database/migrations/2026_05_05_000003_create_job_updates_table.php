<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The job conversation (D-43). A null user_id marks a system message —
     * status changes and lifecycle events written into the thread. Evidence
     * attaches through the polymorphic attachments table.
     */
    public function up(): void
    {
        Schema::create('job_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('body');
            $table->timestamps();

            $table->index(['maintenance_job_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_updates');
    }
};
