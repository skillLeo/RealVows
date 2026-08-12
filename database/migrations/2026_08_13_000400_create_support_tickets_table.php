<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support requests from members and from trusted contacts.
 *
 * ⚠️ A TICKET IS NOT A CONVERSATION. It is a request, with a type and a state.
 * The conversation happens in `admin_messages`, which already exists and
 * already carries the relay prohibition in its interface. Building a second
 * threading system here would give staff two inboxes and a reason to answer in
 * the wrong one.
 *
 * ⚠️ THE TYPE MATTERS MORE THAN THE BODY. "My trusted contact is not
 * responding fairly" is the one this table exists for: it is the only route a
 * member has to challenge a decision she cannot see, made by somebody she
 * cannot overrule. It is auto-suggested to her after five unexplained declines
 * and it must never be buried in a general contact form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->text('body');
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
