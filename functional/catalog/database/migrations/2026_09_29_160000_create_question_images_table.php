<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->nullable()->index()->constrained('questions')->restrictOnDelete();
            $table->foreignId('uploader_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('alt', 250)->nullable();
            $table->smallInteger('position')->nullable();
            $table->smallInteger('width');
            $table->smallInteger('height');
            $table->jsonb('variant_widths');
            $table->timestamps();

            $table->index(['question_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_images');
    }
};
