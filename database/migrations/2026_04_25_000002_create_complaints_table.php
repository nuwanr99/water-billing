<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member-raised service complaints (spec §5.6). Threaded and evidence-
     * bearing; "assigned" is derived from the complaint_assignees pivot, so
     * this table only carries the ticket's own state.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('water_account_id')->nullable()->constrained();
            $table->string('category');
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open');
            $table->text('closure_note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->dateTime('submitted_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
