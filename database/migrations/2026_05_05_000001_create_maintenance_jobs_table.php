<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maintenance work orders (D-04, D-42). A job may be linked to a
     * complaint (not unique — one complaint can spawn several jobs) or stand
     * alone as ad-hoc/preventive work. Assignees live in a pivot (D-49).
     */
    public function up(): void
    {
        Schema::create('maintenance_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->unique();
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description');
            $table->date('scheduled_date');
            $table->string('status')->default('assigned');
            $table->text('completion_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_date');
            $table->index('complaint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_jobs');
    }
};
