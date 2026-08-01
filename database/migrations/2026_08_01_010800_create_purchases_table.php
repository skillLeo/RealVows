<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ⚠️ PHASE 2 TABLE — CREATED NOW, DELIBERATELY WITHOUT BUSINESS LOGIC.
     *
     * Three one-time packages, no subscriptions anywhere in this product:
     *   single = 1 match  · US$20
     *   triple = 3 matches · US$50
     *   octet  = 8 matches · US$100
     *
     * Money is stored in integer cents. Floats do not belong anywhere near a
     * currency amount.
     *
     * `gateway_transaction_id` is UNIQUE here for the same reason as on the
     * ledger: a retried gateway callback must never create a second purchase.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('package_key', ['single', 'triple', 'octet']);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->unsignedSmallInteger('credits_granted');

            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');

            $table->string('gateway', 32)->nullable();
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->json('gateway_response')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
