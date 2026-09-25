<?php

namespace Functional\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Limits what a reader sees, not the markup: 1 to 5,000 characters of text (FR-014).
 */
class VisibleTextLength implements ValidationRule
{
    public const MAX = 5000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $visibleText = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
        $length = mb_strlen($visibleText);

        if ($length === 0) {
            $fail('validation.required')->translate();
        } elseif ($length > self::MAX) {
            $fail('validation.max.string')->translate(['max' => self::MAX]);
        }
    }
}
