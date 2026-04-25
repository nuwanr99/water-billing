<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The handler(s) who own a complaint (D-48). Usually one row; the first
     * assignment moves the complaint from open to in_progress and the
     * notification audience narrows to these users.
     */
    public function up(): void
    {
        Schema::create('complaint_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->dateTime('assigned_at');
            $table->timestamps();

            $table->unique(['complaint_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_assignees');
    }
};
