<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual, human verification — the entire trust model of the product.
     * A member photographs themselves holding a handwritten note carrying
     * the generated code; a real person compares it against their four
     * photos and approves or rejects with a stored reason.
     */
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['pending', 'verified', 'rejected', 'review'])->default('pending');
            $table->string('code', 16);

            // Stored apart from the profile photos and flagged so it can
            // never be rendered on a profile or shown to another member.
            $table->string('selfie_path')->nullable();
            $table->string('selfie_webp_path')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('rejection_note')->nullable();
            $table->unsignedTinyInteger('attempt_number')->default(1);

            $table->timestamps();

            // The admin queue reads pending, oldest first.
            $table->index(['status', 'created_at']);
            $table->index('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
