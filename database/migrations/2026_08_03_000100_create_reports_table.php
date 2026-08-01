<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member's way of saying something is wrong.
     *
     * On a platform where people pay to be introduced to strangers, having no
     * way to report anyone is a real hole — and the absence is felt most by
     * exactly the people least able to speak up.
     *
     * ⚠️ The reported member is never told. Not that a report exists, not who
     * made it, not that anything is being looked at. A reporting system that
     * leaks back to its subject is worse than none, because it invites
     * retaliation against somebody who did the right thing.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('subject_profile_id')->constrained('profiles')->cascadeOnDelete();

            // Which introduction it arose from, so a reviewer has the context.
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();

            $table->enum('reason', [
                'misrepresented_identity',
                'inappropriate_content',
                'appears_to_be_scam',
                'other',
            ]);

            $table->text('note')->nullable();

            $table->enum('status', ['open', 'reviewed', 'actioned', 'dismissed'])->default('open');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('subject_profile_id');

            /*
             * One open report per person per subject. Somebody tapping twice
             * is not two concerns, and a queue full of duplicates is a queue
             * nobody works through.
             */
            $table->unique(
                ['reporter_profile_id', 'subject_profile_id', 'status'],
                'reports_one_open_per_pair'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
