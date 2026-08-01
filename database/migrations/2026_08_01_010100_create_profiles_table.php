<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // --- Step 2: Personal -------------------------------------------------
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable(); // private until mutual interest
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['man', 'woman', 'non_binary'])->nullable();
            $table->enum('seeking', ['man', 'woman', 'non_binary'])->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->boolean('willing_to_relocate')->nullable();
            $table->string('nationality')->nullable();

            /*
             * ⚠️ INTERNAL ONLY — never selected into an Inertia prop, an API
             * response, an email, or any other surface a member can reach.
             * Derived silently from date_of_birth on save. These two columns
             * feed the Phase 2 scoring matrix and nothing else. See the
             * $hidden array on the Profile model, which is the second guard.
             */
            $table->string('sign', 16)->nullable();
            $table->string('element', 16)->nullable();

            // --- Step 3: Physical -------------------------------------------------
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->enum('body_type', [
                'slim', 'athletic', 'average', 'curvy', 'plus_size', 'prefer_not_to_say',
            ])->nullable();
            $table->string('eye_colour', 32)->nullable();
            $table->string('hair_colour', 32)->nullable();
            $table->boolean('has_tattoos')->nullable();
            $table->boolean('has_piercings')->nullable();

            // --- Step 5: Lifestyle ------------------------------------------------
            $table->string('education')->nullable();
            $table->string('occupation')->nullable();
            $table->string('industry')->nullable();
            $table->string('income_range')->nullable();
            $table->string('religion')->nullable();
            $table->enum('faith_importance', ['not_important', 'somewhat', 'important', 'essential'])->nullable();
            $table->enum('smoking', ['never', 'socially', 'regularly'])->nullable();
            $table->enum('drinking', ['never', 'socially', 'regularly'])->nullable();
            $table->enum('has_children', ['none', 'yes_at_home', 'yes_independent'])->nullable();
            $table->enum('wants_children', ['yes', 'no', 'open', 'undecided'])->nullable();
            $table->json('pets')->nullable();
            $table->json('languages')->nullable();

            // --- Step 6: Values, goals, personality -------------------------------
            $table->string('relationship_goal')->nullable();
            $table->enum('marriage_timeline', ['within_year', 'one_to_two', 'two_plus', 'no_fixed'])->nullable();
            $table->json('core_values')->nullable();      // ordered top five
            $table->enum('conflict_style', ['discuss_now', 'need_space', 'seek_compromise', 'avoid'])->nullable();
            $table->json('love_languages')->nullable();
            $table->text('bio')->nullable();              // 150–300 words
            $table->json('interests')->nullable();
            $table->json('personality_scales')->nullable();

            // --- Builder state ----------------------------------------------------
            $table->unsignedTinyInteger('completeness_percent')->default(0);
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            // Columns the Phase 2 hard filters will run on.
            $table->index(['gender', 'seeking']);
            $table->index('date_of_birth');
            $table->index('country');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
