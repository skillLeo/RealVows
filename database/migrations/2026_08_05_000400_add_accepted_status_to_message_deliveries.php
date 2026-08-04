<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️ "ACCEPTED BY THE MAIL SERVER" IS NOT "DELIVERED", AND CALLING IT
 * DELIVERED IS THE VACUOUS ASSERTION PATTERN AT THE INFRASTRUCTURE LEVEL.
 *
 * The WhatsApp side earns its statuses: Meta posts back sent, delivered, read
 * and failed, and each one is a fact about a real handset.
 *
 * Email on Hostinger SMTP gives us exactly one fact — the mail server took the
 * message. After that we know nothing. A hard bounce to a dead address, a
 * silent drop into a spam folder, and a message read twice all look identical
 * from here, and all of them look like success.
 *
 * A ledger row that says `delivered` on the strength of a 250 response is
 * reporting success while checking nothing, which is the same failure this
 * project has now been bitten by seven times — only this time the thing
 * lying is an admin screen the client uses to decide whether a member was
 * told about an introduction they paid for.
 *
 * So `accepted` exists to mean precisely what we know: handed over, nothing
 * more. It sits BELOW `delivered` on the ladder, and no email row may ever
 * climb past it while the transport cannot report a bounce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->string('status', 20)->default('queued')->change();
        });

        /*
         * Existing email rows claiming `sent` are re-labelled to what was
         * actually established. There are none in production yet — the
         * fallback has only ever written `queued` — but a migration that is
         * right only because a table is empty is one that breaks the day it
         * is reused.
         */
        DB::table('message_deliveries')
            ->where('channel', 'email')
            ->whereIn('status', ['sent', 'delivered'])
            ->update(['status' => 'accepted']);
    }

    public function down(): void
    {
        DB::table('message_deliveries')
            ->where('status', 'accepted')
            ->update(['status' => 'sent']);

        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])
                ->default('queued')
                ->change();
        });
    }
};
