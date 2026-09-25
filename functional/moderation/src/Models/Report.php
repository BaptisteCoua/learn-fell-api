<?php

namespace Functional\Moderation\Models;

use Functional\Catalog\Models\Subject;
use Functional\Moderation\Database\Factories\ReportFactory;
use Functional\Moderation\Enums\ReportReason;
use Functional\Moderation\Enums\ReportStatus;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;

/**
 * A reader's report on a published subject (FR-027), pending until a moderator decides.
 */
#[Fillable(['subject_id', 'reporter_id', 'reason', 'comment', 'status', 'closed_at'])]
#[UseFactory(ReportFactory::class)]
class Report extends Model
{
    use HasControl, HasFactory;

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'closed_at' => 'datetime',
        ];
    }
}
