<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ⚠️ PHASE 2 TABLE — CREATED NOW, DELIBERATELY WITHOUT BUSINESS LOGIC.
     *
     * Note the column name: `compatibility_rationale`, never
     * `zodiac_rationale`. The generated copy describes what the two members
     * told us about their own values, lifestyle and goals. It must never
     * reference signs or elements, because nothing about the internal
     * mechanism is ever surfaced to a member.
     *
     * `score_breakdown` stores the full component split so every score is
     * auditable after the fact — a member asking "why 87?" has an answer,
     * and the admin match log can show its working.
     *
     * The unique pair stops the engine proposing the same two people twice.
     */
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seeker_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('matched_profile_id')->constrained('profiles')->cascadeOnDelete();

            $table->decimal('total_score', 5, 2);
            $table->json('score_breakdown');
            $table->text('compatibility_rationale')->nullable();

            $table->enum('status', [
                'reserved', 'delivered', 'viewed', 'interested', 'passed', 'expired',
            ])->default('reserved');

            // False until a package is bought. While false the member sees the
            // score and the rationale but no photos — and the locked card is
            // rendered with no image element at all, so there is no URL in the
            // markup for devtools to recover.
            $table->boolean('is_unlocked')->default(false);

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['seeker_profile_id', 'matched_profile_id']);
            $table->index(['seeker_profile_id', 'status']);
            $table->index(['seeker_profile_id', 'is_unlocked']);
            $table->index('total_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
