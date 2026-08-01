<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every interaction with the payment gateway, in both directions.
     *
     * This is what you read at 2am when a member says they were charged and
     * the application disagrees, and it is what the gateway's support desk
     * asks for when they want to know exactly what was sent. Without it the
     * only answer available is "the framework logged a 500 somewhere".
     *
     * Append-only. Nothing updates or deletes a row here.
     *
     * ⚠️ `payload` is written through PaymentRedactor and never receives a
     * card number, a CVV or an expiry date. The redactor runs before the
     * write, not as a display filter, so a leak cannot be introduced later
     * by a new screen reading raw rows.
     */
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();

            $table->enum('direction', ['outbound', 'inbound']);
            $table->string('endpoint');
            $table->unsignedSmallInteger('http_status')->nullable();

            // Null when the request never completed — a timeout is itself a
            // fact worth recording, and is exactly when this table earns out.
            $table->json('payload')->nullable();
            $table->string('note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['purchase_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
