<?php

namespace Functional\Moderation\Access\Controls;

use Functional\Moderation\Models\ModerationDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * Moderators decide; the history is read with `moderation.history.view` and never changed
 * (FR-034).
 */
class ModerationDecisionControl extends Control
{
    protected string $model = ModerationDecision::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view' && $user->can('moderation.history.view'))
                ->should(fn (Model $user, Model $decision): bool => true)
                ->query(fn (Builder $query, Model $user): Builder => $query),

            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'create' && $user->can('subjects.moderate'))
                ->should(fn (Model $user, Model $decision): bool => false)
                ->query(fn (Builder $query, Model $user): Builder => $query->whereRaw('1 = 0')),
        ];
    }
}
