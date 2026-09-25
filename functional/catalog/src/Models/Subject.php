<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Database\Factories\SubjectFactory;
use Functional\Catalog\Enums\SubjectStatus;
use Functional\Catalog\Events\SubjectDeleting;
use Functional\Catalog\Events\SubjectSaving;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

#[Fillable(['title', 'description', 'category_id', 'author_id'])]
#[UseFactory(SubjectFactory::class)]
class Subject extends Model
{
    use HasControl, HasFactory;

    public const MAX_TAGS = 10;

    public const MAX_QUESTIONS = 500;

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'saving' => SubjectSaving::class,
        'deleting' => SubjectDeleting::class,
    ];

    protected $attributes = [
        'status' => 'draft',
        'description' => '',
        'search_document' => '',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->using(SubjectTag::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * The description is optional: an empty one is stored as an empty text.
     *
     * @return Attribute<string, string|null>
     */
    protected function description(): Attribute
    {
        return Attribute::make(set: fn (?string $description): string => $description ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => SubjectStatus::class,
            'published_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
