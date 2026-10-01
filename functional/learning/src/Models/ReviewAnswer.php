<?php

namespace Functional\Learning\Models;

use Functional\Learning\Database\Factories\ReviewAnswerFactory;
use Functional\Learning\Enums\AnswerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One self-assessment during a review (FR-048), kept for the end-of-session summary and for the
 * replay of its card when an earlier answer arrives late (feature 006).
 */
#[Fillable(['answer_id', 'card_progress_id', 'user_id', 'known', 'from_box', 'to_box', 'answered_at', 'due_on', 'status'])]
#[UseFactory(ReviewAnswerFactory::class)]
class ReviewAnswer extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<CardProgress, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(CardProgress::class, 'card_progress_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'known' => 'boolean',
            'from_box' => 'integer',
            'to_box' => 'integer',
            'answered_at' => 'datetime',
            'due_on' => 'date:Y-m-d',
            'status' => AnswerStatus::class,
        ];
    }
}
