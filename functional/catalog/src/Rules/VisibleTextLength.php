<?php

namespace Functional\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Limits what a reader sees, not the markup: 1 to 5,000 characters of text (FR-014). A recto
 * may be empty when it carries images (FR-008): QuestionResource checks that with the images
 * the question will have, and answers `recto_empty` otherwise.
 */
class VisibleTextLength implements ValidationRule
{
    public const MAX = 5000;

    public function __construct(private readonly bool $allowsEmpty = false) {}

    public static function of(string $html): int
    {
        return mb_strlen(trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $length = self::of((string) $value);

        if ($length === 0 && ! $this->allowsEmpty) {
            $fail('validation.required')->translate();
        } elseif ($length > self::MAX) {
            $fail('validation.max.string')->translate(['max' => self::MAX]);
        }
    }
}
