<?php

namespace Functional\Catalog\Access\Controls;

use Functional\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * Everyone reads the categories; only category managers change them.
 */
class CategoryControl extends Control
{
    protected string $model = Category::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view' || $user->can('categories.manage'))
                ->should(fn (Model $user, Model $category): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query),
        ];
    }
}
