<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this member last messaged us on WhatsApp.
 *
 * ⚠️ THIS COLUMN IS THE 24-HOUR CUSTOMER SERVICE WINDOW, AND THE WINDOW IS
 * META'S RULE RATHER THAN A PREFERENCE.
 *
 * Outside 24 hours of a member's own inbound message, only an approved
 * template may be sent. Free text is refused by the API — so without this we
 * would discover the rule at the point of sending, as an error, after the
 * member was already expecting to hear from us.
 *
 * Nullable, and null is the common case: most members never message us at
 * all, which means the window is closed and templates are the only option.
 * That is the normal state of this product, not an edge case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('whatsapp_last_inbound_at')->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('whatsapp_last_inbound_at');
        });
    }
};
