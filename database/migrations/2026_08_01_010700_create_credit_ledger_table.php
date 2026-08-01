<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ⚠️ PHASE 2 TABLE — CREATED NOW, DELIBERATELY WITHOUT BUSINESS LOGIC.
     *
     * It is created in Phase 1 because its safety guarantee lives in the
     * SCHEMA, not in the callback handler, and schema is far cheaper to get
     * right while the table is still empty.
     *
     * Two rules this shape enforces:
     *
     *  1. `gateway_transaction_id` is UNIQUE. A payment gateway retrying a
     *     callback is normal, not exceptional. The unique index makes a
     *     double-credit impossible at the database level — no application
     *     code has to remember to be careful.
     *
     *  2. Balance is ALWAYS derived by summing `delta`, never stored as a
     *     mutable column on users. There is no counter to drift, and every
     *     credit a member has can be traced to the row that granted it.
     *     `balance_after` is a denormalised read convenience for the ledger
     *     view only — it is never the source of truth.
     */
    public function up(): void
    {
        Schema::create('credit_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('delta'); // negative when a match is reserved
            $table->integer('balance_after');

            $table->enum('reason', [
                'purchase',
                'match_reserved',
                'match_refunded',
                'admin_adjustment',
            ]);

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // The idempotency guarantee.
            $table->string('gateway_transaction_id')->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_ledger');
    }
};
