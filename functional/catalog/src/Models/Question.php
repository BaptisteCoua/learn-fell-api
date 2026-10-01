<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Casts\SanitizedHtml;
use Functional\Catalog\Database\Factories\QuestionFactory;
use Functional\Catalog\Events\QuestionCreated;
use Functional\Catalog\Events\QuestionDeleting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

#[Fillable(['subject_id', 'recto_html', 'verso_html', 'position', 'import_id'])]
#[Hidden(['import_id'])]
#[UseFactory(QuestionFactory::class)]
class Question extends Model
{
    use HasControl, HasFactory;

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => QuestionCreated::class,
        'deleting' => QuestionDeleting::class,
    ];

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The images of the recto, in the order the author chose (FR-005).
     *
     * @return HasMany<QuestionImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(QuestionImage::class)->orderBy('position');
    }

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'recto_html' => SanitizedHtml::class,
            'verso_html' => SanitizedHtml::class,
        ];
    }
}
