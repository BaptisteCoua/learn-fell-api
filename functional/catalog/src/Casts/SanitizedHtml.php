<?php

namespace Functional\Catalog\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Facades\Purify;

/**
 * Rich text is cleaned on the way in (FR-015): scripts, event attributes and `javascript:`
 * links never reach the database. Links open as user content: `noopener nofollow ugc`.
 *
 * @implements CastsAttributes<string, string>
 */
class SanitizedHtml implements CastsAttributes
{
    public const LINK_REL = 'noopener nofollow ugc';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $cleanHtml = Purify::clean((string) $value);

        return str_replace('<a href=', '<a rel="'.self::LINK_REL.'" href=', $cleanHtml);
    }
}
