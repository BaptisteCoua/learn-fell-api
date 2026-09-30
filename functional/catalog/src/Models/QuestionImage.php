<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Database\Factories\QuestionImageFactory;
use Functional\Catalog\Events\QuestionImageDeleted;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;

/**
 * An image on the recto of a question (FR-001). It is pending, visible to its uploader only,
 * until the mutate of a question attaches it (data-model.md).
 */
#[Fillable(['alt', 'position'])]
#[UseFactory(QuestionImageFactory::class)]
class QuestionImage extends Model
{
    use HasControl, HasFactory, Prunable;

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'deleted' => QuestionImageDeleted::class,
    ];

    /**
     * An image never attached to a saved question is not kept (FR-018).
     *
     * @return Builder<QuestionImage>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('question_id')
            ->where('created_at', '<', now()->subHours(config('catalog.images.pending_hours')));
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function isPending(): bool
    {
        return $this->question_id === null;
    }

    /**
     * Where the WebP variants live on the images disk, one file per width.
     */
    public function directory(): string
    {
        return "question-images/{$this->getKey()}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant_widths' => 'array',
        ];
    }
}
