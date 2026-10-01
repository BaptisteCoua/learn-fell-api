<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A subject its author left to the community outlives the account: its author becomes null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->change();
        });

        Schema::table('question_images', function (Blueprint $table) {
            $table->foreignId('uploader_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('question_images', function (Blueprint $table) {
            $table->foreignId('uploader_id')->nullable(false)->change();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable(false)->change();
        });
    }
};
