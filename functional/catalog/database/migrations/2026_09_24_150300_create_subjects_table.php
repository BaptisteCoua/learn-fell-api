<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('title', 120);
            $table->text('description')->default('');
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->text('retired_reason')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->text('search_document')->default('');
            $table->timestamps();
        });

        DB::statement('CREATE INDEX subjects_search_document_trgm ON subjects USING gin (search_document gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
