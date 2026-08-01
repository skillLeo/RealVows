<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every third-party credential lives here, not in .env.
     *
     * The client is completely non-technical and will never edit a file on a
     * server. He changes a mail password or a gateway key in the browser.
     *
     * What deliberately stays in .env: APP_KEY, APP_ENV, APP_DEBUG, APP_URL
     * and DB_* — the framework needs those before the database is reachable,
     * so they cannot live in the database.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('group', 64);
            $table->string('key', 64);

            // Encrypted at rest when is_secret; ciphertext is longer than the
            // plaintext, so this is TEXT rather than a sized string.
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);

            $table->string('label');
            $table->string('description')->nullable();
            $table->enum('type', ['text', 'password', 'email', 'url', 'select', 'toggle', 'textarea'])
                ->default('text');
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
