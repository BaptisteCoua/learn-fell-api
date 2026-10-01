<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The import that created a question, so that a confirmation sent twice adds nothing the
 * second time (specs/008-question-import, research R6). Null for a question written by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->uuid('import_id')->nullable();
            $table->index(['subject_id', 'import_id']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'import_id']);
            $table->dropColumn('import_id');
        });
    }
};
