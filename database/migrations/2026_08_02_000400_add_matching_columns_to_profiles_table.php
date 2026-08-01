<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            /*
             * Turns body type from a scored preference into an eliminating
             * one. Off by default and deliberately so: a deal-breaker
             * removes people entirely, and that should always be a decision
             * somebody made on purpose rather than a default they inherited.
             */
            $table->boolean('body_type_is_dealbreaker')->default(false)->after('body_type');

            $table->timestamp('last_matched_at')->nullable();

            /*
             * A hash of only the fields that affect scoring.
             *
             * Somebody fixing a typo in their occupation should not trigger
             * a full re-run against every verified member on the platform.
             * Comparing this hash answers "did anything the engine reads
             * actually change?" in one string comparison.
             */
            $table->string('matching_fields_hash', 64)->nullable();

            $table->index('last_matched_at');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['last_matched_at']);
            $table->dropColumn(['body_type_is_dealbreaker', 'last_matched_at', 'matching_fields_hash']);
        });
    }
};
