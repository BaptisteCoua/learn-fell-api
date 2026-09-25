<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_progress_id')->constrained('card_progress')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('known');
            $table->smallInteger('from_box');
            $table->smallInteger('to_box');
            $table->timestamp('answered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_answers');
    }
};
