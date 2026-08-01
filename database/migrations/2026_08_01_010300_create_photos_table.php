<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos are ROWS, never four columns on the profile.
     *
     * The photo count has already changed once in this project (six to four).
     * Rows absorb a third change for free; columns would mean a migration,
     * a model change, and a form rewrite every time.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            // Exactly four slots. Never five, never six, never "up to".
            $table->enum('slot', ['passport', 'full_length', 'social', 'open']);

            // Paths on a private disk. Never public, never hotlinkable —
            // every read goes through a signed, expiring URL behind an
            // authorisation check.
            $table->string('path');
            $table->string('webp_path')->nullable();
            $table->string('thumbnail_path')->nullable();

            $table->string('original_filename');
            $table->string('mime', 64);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['profile_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
