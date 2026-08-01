<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('email_matches')->default(true);

            // Built now, connected in Phase 3. The toggle is honest about
            // that in the UI rather than silently doing nothing.
            $table->boolean('whatsapp_matches')->default(false);

            /*
             * Deliberately not togglable anywhere in the product. A receipt
             * for money taken is a record the member is entitled to, not a
             * marketing preference. The column exists so the intent is
             * legible in the schema rather than implied by its absence.
             */
            $table->boolean('email_receipts')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
