<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The trusted-contact system.
 *
 * ⚠️ CALLED `guardian` IN CODE AND "TRUSTED CONTACT" ON EVERY SCREEN.
 *
 * The client asked for it never to read as religious machinery to a member.
 * The internal name stays `guardian` because that is what it is and renaming a
 * domain concept to match its marketing label makes both harder to reason
 * about. CopyDeckTest owns the member-facing wording.
 *
 * ⚠️ NO GOVERNMENT ID IS COLLECTED HERE OR ANYWHERE ELSE IN THIS SYSTEM, AND
 * THAT IS A DECISION RATHER THAN AN OMISSION.
 *
 * A passport proves who somebody is. It does not prove that this man is that
 * woman's father, which is the only claim that matters here, and Caribbean
 * documents largely cannot establish that relationship at all. So collecting
 * identity documents would create a store of the most sensitive data on the
 * platform in exchange for evidence of the wrong thing. The joint photograph
 * is the primary check instead: two people, together, holding a code we
 * generated, judged by a human of the appropriate gender.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();

            // The member this person is trusted contact FOR.
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();

            // The guardian's own account. Null until they accept the invitation
            // and set a password: a guardian is a person with a login, not a
            // row of contact details somebody typed on their behalf.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('relationship', 32);
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();

            /*
             * ⚠️ THE FLAG THE WHOLE PHOTOGRAPH RULE HANGS ON.
             *
             * A family guardian is a mahram and may see the other party's
             * photographs. An APPOINTED guardian is a stranger the platform
             * put in that seat because the member had nobody, and must never
             * see a photograph of anyone. GuardianPresenter enforces it at the
             * single emission point.
             */
            $table->boolean('is_appointed')->default(false);

            $table->string('status', 16)->default('invited');

            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->json('risk_reasons')->nullable();
            $table->string('risk_model_version')->nullable();

            $table->string('joint_photo_path')->nullable();

            /*
             * ⚠️ THE CODE THE PAIR MUST BE HOLDING IN THE PHOTOGRAPH.
             *
             * Generated per guardian and shown only after they have signed in
             * to their own account. That ordering is the check: a photograph of
             * two people holding a code that was issued minutes ago cannot be
             * an old picture found on a phone, and the person who received the
             * code had to hold the invitation and set a password to see it.
             */
            $table->string('joint_photo_code', 8)->nullable();
            $table->timestamp('joint_photo_uploaded_at')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            // Hashed, never raw. A fingerprint is a tracking identifier and we
            // only ever need to answer "is this the same device as the
            // member's", which a hash answers perfectly well.
            $table->string('device_fingerprint_hash', 64)->nullable();

            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index('phone');
            $table->index('email');
        });

        /*
         * ⚠️ APPEND-ONLY. A decision is a fact about what somebody chose at a
         * moment in time. Nothing updates a row here; a changed mind is a new
         * row. The support flow in B9 reads this history to judge whether a
         * guardian is behaving unreasonably, and a mutable log cannot answer
         * that question honestly.
         */
        Schema::create('guardian_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->cascadeOnDelete();
            $table->string('gate', 24);
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['guardian_id', 'gate']);
            $table->index(['match_id', 'gate']);
        });

        Schema::create('guardian_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();

            // Hashed. A token in the database is a password to somebody else's
            // account, and a database dump should not hand over live links.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_invitations');
        Schema::dropIfExists('guardian_decisions');
        Schema::dropIfExists('guardians');
    }
};
