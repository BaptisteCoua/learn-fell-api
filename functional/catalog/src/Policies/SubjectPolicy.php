<?php

namespace Functional\Catalog\Policies;

use Functional\Catalog\Access\Controls\SubjectControl;
use Illuminate\Database\Eloquent\Model;

class SubjectPolicy extends PubliclyReadablePolicy
{
    protected string $control = SubjectControl::class;

    protected function isPubliclyReadable(Model $model): bool
    {
        return $model->status->isPublic();
    }
}
