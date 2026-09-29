<?php

namespace Functional\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Technical\Osdd\Exceptions\BusinessRuleException;

/**
 * A recto carries 4 images at most (FR-003). lomkit validates each element of
 * `relations.images` on its own, so the rule counts the kept images from the request, and
 * answers with the business code before any position error: with 5 images, no set of
 * positions can be valid.
 */
class WithinRectoImageLimit implements DataAwareRule, ValidationRule
{
    /**
     * Runs even on an image sent without a position.
     */
    public bool $implicit = true;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $siblings = data_get($this->data, Str::beforeLast(Str::beforeLast($attribute, '.attributes.'), '.'), []);
        $maxImages = config('catalog.images.max_per_recto');

        $keptImages = collect($siblings)->filter(fn (mixed $sibling): bool => is_array($sibling)
            && ($sibling['operation'] ?? null) === 'update');

        if ($keptImages->count() > $maxImages) {
            throw new BusinessRuleException('recto_image_limit', replace: ['max' => $maxImages]);
        }
    }
}
