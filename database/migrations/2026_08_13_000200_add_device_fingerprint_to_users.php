<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The member's device fingerprint, for the one comparison that matters.
 *
 * ⚠️ HASHED, NEVER RAW. A device fingerprint is a tracking identifier — the
 * kind of thing an advertiser builds a profile from. The only question this
 * product ever asks of it is "is this the same device as the trusted
 * contact's", which a hash answers exactly as well while being useless to
 * anybody who gets hold of the table.
 *
 * ⚠️ AND IT IS THE STRONGEST SIGNAL IN RISK SCORING. One device operating both
 * accounts means one person is performing a check designed to involve two.
 * `guardians.device_fingerprint_hash` already existed; without this column
 * there was nothing to compare it against, so the highest-value rule in the
 * scorer could never fire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('device_fingerprint_hash', 64)->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('device_fingerprint_hash');
        });
    }
};
