<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('local_date');
            $table->integer('cards_count');
            $table->jsonb('subject_ids');
            $table->jsonb('channels');
            $table->timestamp('sent_at');

            // At most one reminder a day for an account (FR-007).
            $table->unique(['user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_sends');
    }
};
