<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 24);
            $table->string('comment', 500)->nullable();
            $table->string('status', 8)->default('pending');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // One pending report per person and subject (FR-028).
        DB::statement("CREATE UNIQUE INDEX reports_one_pending_per_reporter ON reports (subject_id, reporter_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
