<?php

namespace Functional\Learning\Access\Controls;

use Functional\Learning\Models\Learning;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * An account reaches its own learnings only (FR-043), moderators included.
 */
class LearningControl extends Control
{
    protected string $model = Learning::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => in_array($method, ['view', 'create', 'delete'], true)
                    && $user->hasVerifiedEmail())
                ->should(fn (Model $user, Model $learning): bool => $learning->user_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->where('learnings.user_id', $user->getKey())),
        ];
    }
}
