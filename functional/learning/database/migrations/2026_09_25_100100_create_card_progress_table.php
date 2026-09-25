<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_id')->constrained('learnings')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('question_id')->constrained('questions')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->smallInteger('box');
            $table->date('next_review_on');
            $table->timestamp('last_answered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'question_id']);
            $table->index(['user_id', 'subject_id', 'next_review_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_progress');
    }
};
