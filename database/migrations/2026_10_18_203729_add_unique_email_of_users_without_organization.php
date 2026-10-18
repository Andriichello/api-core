<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: make emails unique among the users without an
     * organization too, which `unique(organization_id, email)` doesn't, as
     * its NULLs are all distinct. That index stays, as MySQL needs it for
     * the foreign key of `organization_id`.
     */
    public function up(): void
    {
        DB::statement(
            'create unique index users_organization_key_email_unique'
            . ' on users ((coalesce(organization_id, 0)), email)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_organization_key_email_unique');
        });
    }
};
