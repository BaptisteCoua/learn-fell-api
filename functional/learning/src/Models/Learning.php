<?php

namespace Functional\Learning\Models;

use Functional\Catalog\Models\Subject;
use Functional\Learning\Database\Factories\LearningFactory;
use Functional\Learning\Events\LearningCreated;
use Functional\Learning\Events\LearningDeleting;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

/**
 * An account learning a subject (FR-041): one card per question of the subject.
 */
#[Fillable(['user_id', 'subject_id'])]
#[UseFactory(LearningFactory::class)]
class Learning extends Model
{
    use HasControl, HasFactory;

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => LearningCreated::class,
        'deleting' => LearningDeleting::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return HasMany<CardProgress, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(CardProgress::class);
    }
}
