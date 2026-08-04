<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One member deciding never to be shown another, in either direction.
 *
 * ⚠️ BLOCKING IS SYMMETRIC EVEN THOUGH IT IS ONE-SIDED TO DECLARE.
 *
 * The blocker must never see the blocked, and — just as importantly — the
 * blocked must never see the blocker. A one-directional block leaves the
 * blocked party still receiving the blocker as an introduction, still able to
 * express interest in them, and still able to work out from the silence that
 * something happened. On a platform this small, in a country this small, that
 * is how a block becomes an escalation.
 *
 * ⚠️ AND IT IS SILENT. Nothing tells the blocked party. Their experience is
 * that this person simply never appears, which is indistinguishable from the
 * engine not having matched them — which is the normal experience of almost
 * everybody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blocker_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('blocked_profile_id')->constrained('profiles')->cascadeOnDelete();

            /*
             * Optional and never shown to the blocked party. It exists so an
             * administrator reviewing a pattern of blocks against one member
             * has something to read — a member blocked by six people for the
             * same stated reason is a safeguarding signal, and the only place
             * that signal can come from is here.
             */
            $table->string('reason')->nullable();

            $table->timestamps();

            // Blocking twice is the same block, not two.
            $table->unique(['blocker_profile_id', 'blocked_profile_id']);

            /*
             * ⚠️ BOTH DIRECTIONS ARE INDEXED, because the hard filter asks
             * "is there a block either way round?" on every candidate query.
             * Without the reverse index that becomes a full scan inside the
             * one query the whole match run is budgeted around.
             */
            $table->index('blocker_profile_id');
            $table->index('blocked_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
