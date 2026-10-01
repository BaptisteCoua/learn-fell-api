<?php

use Functional\Learning\Enums\AnswerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Answers given offline arrive late and possibly twice (feature 006): each one keeps the id its
 * device gave it, the due date it answered and whether the replay of its card applied it. The
 * answers already recorded were all applied, on the day they were given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_answers', function (Blueprint $table) {
            $table->uuid('answer_id')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 16)->nullable();
        });

        DB::update(
            <<<'SQL'
                UPDATE review_answers
                SET answer_id = gen_random_uuid(),
                    due_on = (review_answers.answered_at AT TIME ZONE ? AT TIME ZONE users.timezone)::date,
                    status = ?
                FROM users
                WHERE users.id = review_answers.user_id
                SQL,
            [config('app.timezone'), AnswerStatus::Applied->value],
        );

        Schema::table('review_answers', function (Blueprint $table) {
            $table->uuid('answer_id')->nullable(false)->change();
            $table->date('due_on')->nullable(false)->change();
            $table->string('status', 16)->nullable(false)->change();

            $table->unique('answer_id');
            $table->index(['card_progress_id', 'answered_at']);
        });
    }

    public function down(): void
    {
        Schema::table('review_answers', function (Blueprint $table) {
            $table->dropIndex(['card_progress_id', 'answered_at']);
            $table->dropUnique(['answer_id']);
            $table->dropColumn(['answer_id', 'due_on', 'status']);
        });
    }
};
