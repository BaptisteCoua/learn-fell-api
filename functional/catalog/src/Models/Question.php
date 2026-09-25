<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Casts\SanitizedHtml;
use Functional\Catalog\Database\Factories\QuestionFactory;
use Functional\Catalog\Events\QuestionCreated;
use Functional\Catalog\Events\QuestionDeleting;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;

#[Fillable(['subject_id', 'recto_html', 'verso_html', 'position'])]
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
