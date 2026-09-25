<?php

namespace Functional\Learning\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One self-assessment during a review (FR-048), kept for the end-of-session summary.
 */
#[Fillable(['card_progress_id', 'user_id', 'known', 'from_box', 'to_box', 'answered_at'])]
class ReviewAnswer extends Model
{
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
        ];
    }
}
