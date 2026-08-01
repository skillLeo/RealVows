<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            $table->unsignedSmallInteger('height_min_cm')->nullable();
            $table->unsignedSmallInteger('height_max_cm')->nullable();

            $table->json('body_types')->nullable();
            $table->string('distance_preference')->nullable();

            // Deal-breakers are eliminating, not scored: they run in the
            // Phase 2 hard-filter pass before any weighting is applied.
            $table->json('deal_breakers')->nullable();
            $table->json('preferred_qualities')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_preferences');
    }
};
