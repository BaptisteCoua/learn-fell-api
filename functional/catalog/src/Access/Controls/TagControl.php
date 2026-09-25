<?php

namespace Functional\Catalog\Access\Controls;

use Functional\Catalog\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * Tags are read by everyone (suggestions while typing); they are only created through subjects.
 */
class TagControl extends Control
{
    protected string $model = Tag::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view')
                ->should(fn (Model $user, Model $tag): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query),
        ];
    }
}
