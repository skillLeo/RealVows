<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per message we tried to send, on either channel.
 *
 * ⚠️ THIS IS A LEDGER, NOT A LOG. It is the only place that can answer "did
 * this member ever actually hear about the introduction they paid for?" — and
 * that question has to be answerable months later, from the admin panel, by
 * somebody who is not a developer.
 *
 * ⚠️ IT DELIBERATELY RECORDS THE VARIABLES THAT WERE SENT.
 *
 * WhatsApp template variables are positional and unnamed. If an order is ever
 * got wrong, the API returns success, the status reads "delivered", and the
 * only evidence of the corruption is what the member read. Storing the values
 * as they were sent is the only way to reconstruct what was actually said.
 *
 * ⚠️ WHAT IS DELIBERATELY NOT STORED: the recipient's phone number in plain
 * text. The `user_id` is the link. A table full of members' numbers, on a host
 * where storage sits inside the web root, is the wrong artefact to create for
 * the sake of a column that a join already provides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * Nullable: not every message concerns one introduction. A
             * verification result or a phone code concerns none.
             */
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();

            $table->enum('channel', ['whatsapp', 'email']);

            // The template name for WhatsApp; the mailable for email.
            $table->string('template');

            /*
             * The ordered values as handed to the provider. See the note above
             * — this is what makes a variable-order corruption provable rather
             * than merely suspected.
             */
            $table->json('variables')->nullable();

            /*
             * ⚠️ `queued` IS NOT `sent`, `sent` IS NOT `delivered`, AND
             * `delivered` IS NOT `read`. Meta reports all four separately and
             * collapsing them is how "we sent it" comes to mean nothing.
             *
             * `failed` is terminal for this row and is what triggers the email
             * fallback.
             */
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])
                ->default('queued');

            // The provider's own id, which is how a status callback finds this row.
            $table->string('provider_message_id')->nullable();

            $table->unsignedTinyInteger('attempts')->default(0);

            /*
             * ⚠️ RETRYABLE IS STORED, NOT INFERRED FROM THE ERROR TEXT.
             *
             * A bad number and a rate limit both arrive as a failure. Deciding
             * which by pattern-matching a message that Meta is free to reword
             * is how a permanent failure starts being retried forever.
             */
            $table->boolean('retryable')->default(false);
            $table->string('error_code')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            /*
             * Set when a WhatsApp failure has been answered by an email, so
             * the fallback cannot fire twice for one message.
             */
            $table->timestamp('fell_back_at')->nullable();

            $table->timestamps();

            // The status callback's lookup. Unique so a repeated callback
            // cannot create a second row for one message.
            $table->unique('provider_message_id');

            // The admin panel's filters: this member's history, newest first.
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');
    }
};
