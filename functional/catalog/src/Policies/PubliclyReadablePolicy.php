<?php

namespace Functional\Catalog\Policies;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Policies\ControlledPolicy;

/**
 * The catalogue is readable without an account (FR-022). Signed-in users go through the
 * model's Control; a visitor may only list and read what isPubliclyReadable() allows.
 */
abstract class PubliclyReadablePolicy extends ControlledPolicy
{
    abstract protected function isPubliclyReadable(Model $model): bool;

    public function viewAny(?Model $user): bool
    {
        return $user === null || parent::viewAny($user);
    }

    public function view(?Model $user, Model $model): bool
    {
        return $user === null ? $this->isPubliclyReadable($model) : parent::view($user, $model);
    }
}
