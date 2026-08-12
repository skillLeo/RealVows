<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep what the member actually wrote.
 *
 * ⚠️ THE ORIGINAL IS STORED ALONGSIDE THE REWRITE, AND THAT IS AN AUDIT
 * REQUIREMENT RATHER THAN A CONVENIENCE.
 *
 * If a member is ever accused of misrepresenting themselves — by another
 * member, or by us during a review — the only fair question is what THEY said.
 * Without this column a rewrite becomes indistinguishable from their own words
 * the moment it is accepted, and the platform would be unable to tell whether a
 * false claim came from the member or from a model the platform ran on their
 * behalf.
 *
 * ⚠️ NULL MEANS THEY WROTE IT THEMSELVES. Not an empty string: a member who
 * never used the writer has no "original" distinct from their bio, and storing
 * a copy would imply one existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->text('bio_original')->nullable()->after('bio');
            $table->string('bio_rewritten_by', 64)->nullable()->after('bio_original');
            $table->timestamp('bio_rewritten_at')->nullable()->after('bio_rewritten_by');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['bio_original', 'bio_rewritten_by', 'bio_rewritten_at']);
        });
    }
};
