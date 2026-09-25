<?php

namespace Functional\Learning\Access\Controls;

use Functional\Learning\Models\CardProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Perimeter;

/**
 * An account reads its own cards only; they change through the `answer` action alone.
 */
class CardProgressControl extends Control
{
    protected string $model = CardProgress::class;

    /**
     * @return list<Perimeter>
     */
    protected function perimeters(): array
    {
        return [
            Perimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $method === 'view')
                ->should(fn (Model $user, Model $card): bool => $card->user_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->where('card_progress.user_id', $user->getKey())),
        ];
    }
}
