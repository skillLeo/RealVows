<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ⚠️ THE SCHOLAR REQUIRES SOMEBODY TO BE PRESENT, NOT MERELY TO APPROVE.
 *
 * "Arranging conversation between the two is not permitted in Shariah.
 * Whatever necessary matters need to be satisfied should be handled through the
 * women of the household."
 *
 * A woman with an appointed contact has no family to carry that, so staff do.
 * An approval row alone would record that somebody allowed it; this records who
 * actually stood in the middle and made the introduction, by name.
 *
 * ⚠️ NULLABLE, because every existing decision was made by a trusted contact
 * rather than by staff, and backfilling a name onto those would be inventing a
 * fact about a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_decisions', function (Blueprint $table) {
            $table->foreignId('made_by_user_id')
                ->nullable()
                ->after('guardian_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guardian_decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('made_by_user_id');
        });
    }
};
