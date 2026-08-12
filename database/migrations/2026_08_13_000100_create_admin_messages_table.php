<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Threads between staff and one member, or staff and one trusted contact.
 *
 * ⚠️ THERE IS NO MEMBER-TO-MEMBER THREAD AND THERE NEVER WILL BE.
 *
 * This table has ONE counterparty column. It is structurally incapable of
 * holding a conversation between two members, which is the point: RealVows
 * introduces people and then gets out of the way. Adding a second party column
 * here would be the first line of building a messaging product.
 *
 * ⚠️ AND STAFF MAY NOT RELAY. An admin who reads a message from one member and
 * types its contents to another has built member-to-member messaging by hand,
 * with themselves as the transport. That cannot be prevented by a schema, so it
 * is written into the interface above the compose box where the person doing it
 * will read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_messages', function (Blueprint $table) {
            $table->id();

            // The one person on the other side. Never two.
            $table->foreignId('counterparty_id')->constrained('users')->cascadeOnDelete();

            // Who wrote this one. Null when the system did.
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('from_staff')->default(false);
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['counterparty_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_messages');
    }
};
