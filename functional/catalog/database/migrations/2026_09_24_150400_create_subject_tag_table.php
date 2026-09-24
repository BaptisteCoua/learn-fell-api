<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_tag', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->restrictOnDelete();
            $table->primary(['subject_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_tag');
    }
};
