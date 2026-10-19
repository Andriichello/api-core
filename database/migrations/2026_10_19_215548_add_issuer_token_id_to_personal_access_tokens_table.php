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
        // The token, with which a switched token was issued. No foreign key, as
        // refreshing replaces the issuer, and passes its switched tokens on.
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('issuer_token_id')->nullable()->after('impersonator_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['issuer_token_id']);
            $table->dropColumn('issuer_token_id');
        });
    }
};
