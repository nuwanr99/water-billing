<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gateway intents (D-36): an online checkout creates a pending payment
     * before any money moves, so receipt_number becomes nullable — the
     * gapless RCPT- series is only consumed when the webhook confirms.
     * public_token is the unguessable reference used in the customer-facing
     * result and PDF-receipt URLs.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->change();
            $table->uuid('public_token')->nullable()->unique()->after('receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('public_token');
            $table->string('receipt_number')->nullable(false)->change();
        });
    }
};
