<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Nothing reads or writes them: Laravel's session handler and password broker have their own columns
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['token_type', 'token_id']);
            $table->dropColumn(['token_id', 'token_type']);
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn(['used_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('token_id')->nullable()->after('user_id');
            $table->string('token_type')->nullable()->after('token_id');

            $table->index(['token_type', 'token_id']);
        });

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable()->after('token');
            $table->timestamp('expires_at')->nullable()->after('used_at');
        });
    }
};
