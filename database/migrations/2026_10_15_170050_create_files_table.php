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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('fileable_type');
            $table->unsignedBigInteger('fileable_id');
            $table->string('collection', 64)->default('default');

            $table->string('name');
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Serves the queries of a model's files, and of its files in a collection
            $table->index(['fileable_type', 'fileable_id', 'collection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
