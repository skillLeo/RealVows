<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The mobile number carries WhatsApp match delivery, so it is
            // stored split: a typo in the country code is a different bug
            // from a typo in the number.
            $table->string('phone_country_code', 8)->nullable()->after('password');
            $table->string('phone_number', 32)->nullable()->after('phone_country_code');

            // Phase 1 ships the column and a null-driver service. Nothing
            // sends yet; verification happens over WhatsApp itself in Phase 3,
            // which proves the number receives WhatsApp as well as existing.
            $table->timestamp('phone_verified_at')->nullable()->after('phone_number');

            $table->boolean('is_active')->default(true)->after('phone_verified_at');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->softDeletes();

            $table->index('is_active');
            $table->index(['phone_country_code', 'phone_number']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['phone_country_code', 'phone_number']);

            $table->dropColumn([
                'phone_country_code',
                'phone_number',
                'phone_verified_at',
                'is_active',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
