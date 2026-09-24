<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Database\Factories\TagFactory;
use Functional\Catalog\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
#[UseFactory(TagFactory::class)]
class Tag extends Model
{
    use HasFactory;

    public const MAX_LENGTH = 30;

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class);
    }

    /**
     * Tags are stored normalized, so « Grammaire » and « grammaire  » are one tag.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn (string $name): string => TextNormalizer::normalize($name));
    }
}
