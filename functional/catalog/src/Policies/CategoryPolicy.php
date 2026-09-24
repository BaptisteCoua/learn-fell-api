<?php

namespace Functional\Catalog\Policies;

use Functional\Catalog\Access\Controls\CategoryControl;
use Illuminate\Database\Eloquent\Model;

class CategoryPolicy extends PubliclyReadablePolicy
{
    protected string $control = CategoryControl::class;

    protected function isPubliclyReadable(Model $model): bool
    {
        return true;
    }
}
