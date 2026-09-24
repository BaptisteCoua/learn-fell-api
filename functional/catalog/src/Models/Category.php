<?php

namespace Functional\Catalog\Models;

use Functional\Catalog\Database\Factories\CategoryFactory;
use Functional\Catalog\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'position'])]
#[UseFactory(CategoryFactory::class)]
class Category extends Model
{
    use HasFactory;

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * Setting the name also sets the normalized name that carries its uniqueness.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn (string $name): array => [
            'name' => Str::squish($name),
            'name_normalized' => TextNormalizer::normalize($name),
        ]);
    }
}
