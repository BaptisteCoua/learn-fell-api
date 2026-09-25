<?php

namespace Functional\Learning\Models;

use Functional\Catalog\Models\Question;
use Functional\Catalog\Models\Subject;
use Functional\Learning\Database\Factories\CardProgressFactory;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

/**
 * Where one question stands for one learner: its box and when it comes back (FR-042).
 */
#[Fillable(['learning_id', 'user_id', 'question_id', 'subject_id', 'box', 'next_review_on', 'last_answered_at'])]
#[UseFactory(CardProgressFactory::class)]
class CardProgress extends Model
{
    use HasControl, HasFactory;

    protected $table = 'card_progress';

    /**
     * @return BelongsTo<Learning, $this>
     */
    public function learning(): BelongsTo
    {
        return $this->belongsTo(Learning::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<ReviewAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ReviewAnswer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'box' => 'integer',
            'next_review_on' => 'date:Y-m-d',
            'last_answered_at' => 'datetime',
        ];
    }
}
