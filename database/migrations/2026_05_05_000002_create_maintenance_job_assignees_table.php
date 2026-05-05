<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The user(s) a job is assigned to (D-49). Usually one; crew tasks take
     * several. Any assignee may start or complete the job.
     */
    public function up(): void
    {
        Schema::create('maintenance_job_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->dateTime('assigned_at');
            $table->timestamps();

            $table->unique(['maintenance_job_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_job_assignees');
    }
};
