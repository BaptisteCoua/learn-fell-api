<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports and decisions outlive the account that made them, without its name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('reporter_id')->nullable()->change();
        });

        Schema::table('moderation_decisions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('moderation_decisions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable(false)->change();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('reporter_id')->nullable(false)->change();
        });
    }
};
