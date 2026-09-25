<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_decisions', function (Blueprint $table) {
            $table->id();
            // No foreign key: the history outlives a deleted subject (FR-034).
            $table->unsignedBigInteger('subject_id')->index();
            $table->string('subject_title', 120);
            $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_decisions');
    }
};
