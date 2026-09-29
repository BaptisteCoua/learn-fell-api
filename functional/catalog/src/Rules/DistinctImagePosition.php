<?php

namespace Functional\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Two images of one recto never share a position (FR-005). lomkit validates each element of
 * `relations.images` on its own, so the rule reads its siblings from the request.
 */
class DistinctImagePosition implements DataAwareRule, ValidationRule
{
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

        $samePosition = collect($siblings)->filter(fn (mixed $sibling): bool => is_array($sibling)
            && ($sibling['operation'] ?? null) === 'update'
            && (string) ($sibling['attributes']['position'] ?? '') === (string) $value);

        if ($samePosition->count() > 1) {
            $fail('validation.distinct')->translate();
        }
    }
}
