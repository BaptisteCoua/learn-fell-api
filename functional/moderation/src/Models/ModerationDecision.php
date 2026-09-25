<?php

namespace Functional\Moderation\Models;

use Functional\Moderation\Database\Factories\ModerationDecisionFactory;
use Functional\Moderation\Enums\DecisionType;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;

/**
 * One moderation act, kept forever (FR-034): who, when, what, why.
 */
#[Fillable(['subject_id', 'subject_title', 'admin_id', 'decision', 'reason'])]
#[UseFactory(ModerationDecisionFactory::class)]
class ModerationDecision extends Model
{
    use HasControl, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['decision' => DecisionType::class];
    }
}
