<?php

namespace Functional\Catalog\Policies;

use Functional\Catalog\Access\Controls\QuestionControl;
use Illuminate\Database\Eloquent\Model;

class QuestionPolicy extends PubliclyReadablePolicy
{
    protected string $control = QuestionControl::class;

    protected function isPubliclyReadable(Model $model): bool
    {
        return $model->subject->status->isPublic();
    }
}
