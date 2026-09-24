<?php

namespace Functional\Catalog\Policies;

use Functional\Catalog\Access\Controls\TagControl;
use Illuminate\Database\Eloquent\Model;

class TagPolicy extends PubliclyReadablePolicy
{
    protected string $control = TagControl::class;

    protected function isPubliclyReadable(Model $model): bool
    {
        return true;
    }
}
