<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('email_enabled')->default(false);
            $table->time('send_time')->default('19:00');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('proposal_seen_at')->nullable();
            $table->string('email_disabled_reason', 20)->nullable();
            $table->smallInteger('email_bounce_count')->default(0);
            $table->integer('unsubscribe_version')->default(0);
            $table->timestamp('next_reminder_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_settings');
    }
};
